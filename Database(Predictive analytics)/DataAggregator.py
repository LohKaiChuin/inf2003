"""
Data Aggregator - API Version
Converts stop-level BusVolume data to route-level ridership data for ML training
Uses API instead of direct database connection
"""
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from APIClient import get_api_client
from config import AVAILABLE_ROUTES, API_BASE_URL

class DataAggregator:
    """Aggregates bus volume data for ML training using API"""
    
    def __init__(self, api_base_url=None):
        """
        Initialize DataAggregator with API client
        
        Args:
            api_base_url: Override default API base URL from config
        """
        if api_base_url:
            self.api = get_api_client(api_base_url)
        else:
            self.api = get_api_client(API_BASE_URL)
    
    def aggregate_route_volume(self, service_no, month=None, direction=1):
        """
        Aggregate bus volume for a route
        
        Args:
            service_no: Route service number
            month: Specific month (YYYYMM) or None for all
            direction: Route direction (default 1)
        
        Returns:
            DataFrame with aggregated hourly ridership
        """
        print(f"Aggregating data for Route {service_no}...")
        
        # Get volume data from API (instead of direct DB query)
        df = self.api.get_bus_volume_by_route(service_no, month, direction)
        
        if df.empty:
            print(f"⚠️  No data found for route {service_no}")
            return pd.DataFrame()
        
        # Convert day type to is_weekend
        df['is_weekend'] = df['day'].apply(lambda x: 1 if x == 'H' else 0)
        
        # Extract date components from month (YYYYMM format)
        df['year'] = df['month'].astype(str).str[:4].astype(int)
        df['month_num'] = df['month'].astype(str).str[4:].astype(int)
        
        # Estimate day_of_week (simplified - assume WD=Monday, H=Sunday)
        df['day_of_week'] = df['day'].apply(lambda x: 6 if x == 'H' else 0)
        
        # Add route_id
        df['route_id'] = service_no
        
        # Rename and select relevant columns
        # Drop the original 'month' column and use 'month_num' instead
        result = df[[
            'route_id', 'year', 'month_num', 'day', 'hour',
            'day_of_week', 'is_weekend', 'total_passengers'
        ]].copy()

        # Rename for consistency with training data format
        result = result.rename(columns={
            'total_passengers': 'passengers',
            'month_num': 'month'
        })
        
        print(f"✓ Aggregated {len(result)} hourly records for Route {service_no}")
        print(f"  - Total passengers: {result['passengers'].sum():,}")
        print(f"  - Average hourly: {result['passengers'].mean():.0f}")
        
        return result
    
    def aggregate_all_routes(self, routes=None, month=None):
        """
        Aggregate data for all routes
        
        Args:
            routes: List of route IDs (default: all available routes)
            month: Specific month or None for all
        
        Returns:
            Combined DataFrame for all routes
        """
        if routes is None:
            routes = AVAILABLE_ROUTES
        
        print(f"\n{'='*60}")
        print(f"AGGREGATING DATA FOR {len(routes)} ROUTES")
        print(f"{'='*60}\n")
        
        all_data = []
        
        for route_id in routes:
            try:
                route_data = self.aggregate_route_volume(route_id, month)
                if not route_data.empty:
                    all_data.append(route_data)
            except Exception as e:
                print(f"⚠️  Error processing route {route_id}: {e}")
        
        if not all_data:
            print("✗ No data aggregated")
            return pd.DataFrame()
        
        # Combine all routes
        combined_df = pd.concat(all_data, ignore_index=True)
        
        print(f"\n{'='*60}")
        print(f"AGGREGATION COMPLETE")
        print(f"{'='*60}")
        print(f"Total routes: {combined_df['route_id'].nunique()}")
        print(f"Total records: {len(combined_df):,}")

        # Get scalar values for date range - force scalar with item()
        try:
            min_year = int(combined_df['year'].min().item() if hasattr(combined_df['year'].min(), 'item') else combined_df['year'].min())
            min_month = int(combined_df['month'].min().item() if hasattr(combined_df['month'].min(), 'item') else combined_df['month'].min())
            max_year = int(combined_df['year'].max().item() if hasattr(combined_df['year'].max(), 'item') else combined_df['year'].max())
            max_month = int(combined_df['month'].max().item() if hasattr(combined_df['month'].max(), 'item') else combined_df['month'].max())
            total_pax = int(combined_df['passengers'].sum())
            avg_hourly = float(combined_df['passengers'].mean())

            print(f"Date range: {min_year}-{min_month:02d} to {max_year}-{max_month:02d}")
            print(f"Total passengers: {total_pax:,}")
            print(f"Average hourly: {avg_hourly:.0f}")
        except Exception as e:
            # Fallback: just skip the date range print
            print(f"Total passengers: {int(combined_df['passengers'].sum()):,}")
            print(f"Average hourly: {combined_df['passengers'].mean():.0f}")
        
        return combined_df
    
    def prepare_training_data(self, routes=None, months=None):
        """
        Prepare data for model training
        
        Args:
            routes: List of route IDs (default: all)
            months: List of months in YYYYMM format (default: all available)
        
        Returns:
            DataFrame ready for model training with lag features
        """
        if months is None:
            # Get all available months from API
            available_months = self.api.get_available_months()
            months = available_months
        
        print(f"\nPreparing training data for {len(months)} months...")
        
        # Aggregate data for all specified months
        all_month_data = []
        for month in months:
            print(f"\nProcessing month {month}...")
            month_data = self.aggregate_all_routes(routes, month)
            if not month_data.empty:
                all_month_data.append(month_data)
        
        if not all_month_data:
            print("✗ No training data prepared")
            return pd.DataFrame()
        
        # Combine all months
        df = pd.concat(all_month_data, ignore_index=True)
        
        # Sort by route, date, and hour for lag feature calculation
        df = df.sort_values(['route_id', 'year', 'month', 'hour'])
        
        # Create lag features (previous hour's ridership)
        print("\nCreating lag features...")
        df['prev_hour_passengers'] = df.groupby('route_id')['passengers'].shift(1)
        
        # Fill NaN values in lag feature with route average
        for route_id in df['route_id'].unique():
            route_avg = df[df['route_id'] == route_id]['passengers'].mean()
            df.loc[(df['route_id'] == route_id) & 
                   (df['prev_hour_passengers'].isna()), 'prev_hour_passengers'] = route_avg
        
        # Create a proper date column for reference
        df['date'] = df['year'].astype(str) + '-' + df['month'].astype(str).str.zfill(2) + '-01'
        
        print(f"\n✓ Training data prepared: {len(df):,} records")
        
        return df
    
    def get_route_statistics(self, service_no, month=None):
        """Get statistical summary for a route"""
        df = self.aggregate_route_volume(service_no, month)
        
        if df.empty:
            return None
        
        stats = {
            'route_id': service_no,
            'total_records': len(df),
            'total_passengers': int(df['passengers'].sum()),
            'avg_hourly': float(df['passengers'].mean()),
            'max_hourly': int(df['passengers'].max()),
            'min_hourly': int(df['passengers'].min()),
            'std_hourly': float(df['passengers'].std()),
            'peak_hours': df.nlargest(3, 'passengers')[['hour', 'passengers']].to_dict('records'),
            'weekday_avg': float(df[df['is_weekend'] == 0]['passengers'].mean()),
            'weekend_avg': float(df[df['is_weekend'] == 1]['passengers'].mean())
        }
        
        return stats


# ==================== TESTING ====================

if __name__ == '__main__':
    print("="*60)
    print("DATA AGGREGATOR TEST (API VERSION)")
    print("="*60)
    print()
    
    try:
        aggregator = DataAggregator()
        
        # Test 1: Aggregate single route
        print("[Test 1] Aggregate Route 118 for July 2021")
        route_data = aggregator.aggregate_route_volume('118', month=202107)
        print()
        
        # Test 2: Get route statistics
        print("[Test 2] Get Route 118 Statistics")
        stats = aggregator.get_route_statistics('118', month=202107)
        if stats:
            print(f"  Total passengers: {stats['total_passengers']:,}")
            print(f"  Average hourly: {stats['avg_hourly']:.0f}")
            print(f"  Weekday avg: {stats['weekday_avg']:.0f}")
            print(f"  Weekend avg: {stats['weekend_avg']:.0f}")
            print(f"  Peak hours:")
            for peak in stats['peak_hours']:
                print(f"    - Hour {peak['hour']}: {peak['passengers']} passengers")
        print()
        
        # Test 3: Aggregate multiple routes
        print("[Test 3] Aggregate Top 3 Routes for July 2021")
        multi_route_data = aggregator.aggregate_all_routes(
            routes=['118', '10', '100'], 
            month=202107
        )
        print()
        
        # Test 4: Prepare training data
        print("[Test 4] Prepare Training Data (Multiple Months)")
        training_data = aggregator.prepare_training_data(
            routes=['118'],
            months=[202107, 202108, 202109]
        )
        
        if not training_data.empty:
            print(f"\nTraining Data Sample:")
            print(training_data.head())
            print(f"\nColumns: {training_data.columns.tolist()}")
        
        print("\n" + "="*60)
        print("✓ ALL TESTS PASSED")
        print("="*60)
    
    except Exception as e:
        print(f"\n✗ Test failed: {e}")
        import traceback
        traceback.print_exc()
        print()
        print("Make sure:")
        print("  1. SSH tunnel is active")
        print("  2. PHP server is running: php -S localhost:8000")
        print("  3. analytics_api.php is accessible")