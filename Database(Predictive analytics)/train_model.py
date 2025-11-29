"""
Model Training with MySQL Database Integration
Trains the Random Forest model using real BusVolume data from MySQL
"""
import pandas as pd
import numpy as np
import pickle
import os
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_squared_error, mean_absolute_error, r2_score
from datetime import datetime

from DataAggregator import DataAggregator
from config import RANDOM_STATE, TEST_SIZE, N_ESTIMATORS, MODEL_FILE, AVAILABLE_ROUTES

def load_training_data_from_db(routes=None, months=None):
    """
    Load training data from MySQL database
    
    Args:
        routes: List of route IDs (default: all available)
        months: List of months in YYYYMM format (default: all available)
    
    Returns:
        DataFrame ready for training
    """
    print("="*70)
    print("LOADING TRAINING DATA FROM MYSQL DATABASE")
    print("="*70)
    print()
    
    aggregator = DataAggregator()
    
    # Prepare training data
    df = aggregator.prepare_training_data(routes=routes, months=months)
    
    if df.empty:
        raise ValueError("No training data loaded from database!")
    
    return df

def prepare_features(df):
    """
    Feature engineering for model training
    
    Args:
        df: DataFrame with aggregated route data
    
    Returns:
        DataFrame with engineered features
    """
    print("\n" + "="*70)
    print("FEATURE ENGINEERING")
    print("="*70)
    print()
    
    # Features already in the data:
    # - hour, day_of_week, is_weekend, month, prev_hour_passengers
    
    # Verify all required features exist
    required_features = ['hour', 'day_of_week', 'is_weekend', 'month', 'prev_hour_passengers']
    missing = [f for f in required_features if f not in df.columns]
    
    if missing:
        raise ValueError(f"Missing required features: {missing}")
    
    print("✓ All required features present")
    print(f"  Features: {required_features}")
    
    # Check for NaN values
    nan_counts = df[required_features].isna().sum()
    if nan_counts.any():
        print("\n⚠️  NaN values found:")
        for col, count in nan_counts[nan_counts > 0].items():
            print(f"  - {col}: {count} NaN values")
        
        # Fill NaN values
        for col in required_features:
            if df[col].isna().any():
                if col == 'prev_hour_passengers':
                    df[col].fillna(df['passengers'].mean(), inplace=True)
                else:
                    df[col].fillna(0, inplace=True)
        print("✓ NaN values filled")
    
    print(f"\n✓ Feature engineering complete")
    print(f"  Total samples: {len(df):,}")
    
    return df

def train_model(df):
    """
    Train Random Forest regression model
    
    Args:
        df: DataFrame with features and target
    
    Returns:
        Trained model and evaluation metrics
    """
    print("\n" + "="*70)
    print("MODEL TRAINING")
    print("="*70)
    print()
    
    # Define features and target
    feature_columns = ['hour', 'day_of_week', 'is_weekend', 'month', 'prev_hour_passengers']
    X = df[feature_columns]
    y = df['passengers']
    
    print(f"Features: {feature_columns}")
    print(f"Target: passengers")
    print()
    
    # Train/test split
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=TEST_SIZE, random_state=RANDOM_STATE
    )
    
    print(f"Training samples: {len(X_train):,}")
    print(f"Test samples: {len(X_test):,}")
    print(f"Split ratio: {int((1-TEST_SIZE)*100)}% train / {int(TEST_SIZE*100)}% test")
    print()
    
    # Train model
    print("Training Random Forest model...")
    model = RandomForestRegressor(
        n_estimators=N_ESTIMATORS,
        random_state=RANDOM_STATE,
        n_jobs=-1,
        verbose=0
    )
    
    model.fit(X_train, y_train)
    print("✓ Model training complete")
    print()
    
    # Evaluate
    print("="*70)
    print("MODEL EVALUATION")
    print("="*70)
    print()
    
    y_pred = model.predict(X_test)
    
    # Metrics
    rmse = np.sqrt(mean_squared_error(y_test, y_pred))
    mae = mean_absolute_error(y_test, y_pred)
    r2 = r2_score(y_test, y_pred)
    
    # Accuracy within thresholds
    errors = np.abs(y_test - y_pred)
    within_10 = (errors <= 10).sum() / len(errors) * 100
    within_15 = (errors <= 15).sum() / len(errors) * 100
    within_20 = (errors <= 20).sum() / len(errors) * 100
    within_30 = (errors <= 30).sum() / len(errors) * 100
    
    print("Performance Metrics:")
    print(f"  RMSE:        {rmse:.2f} passengers")
    print(f"  MAE:         {mae:.2f} passengers")
    print(f"  R² Score:    {r2:.3f}")
    print()
    print("Accuracy by Threshold:")
    print(f"  ±10 pax:     {within_10:.1f}%")
    print(f"  ±15 pax:     {within_15:.1f}%")
    print(f"  ±20 pax:     {within_20:.1f}%")
    print(f"  ±30 pax:     {within_30:.1f}%")
    print()
    
    # Feature importance
    feature_importance = pd.DataFrame({
        'feature': feature_columns,
        'importance': model.feature_importances_
    }).sort_values('importance', ascending=False)
    
    print("Feature Importance:")
    for _, row in feature_importance.iterrows():
        print(f"  {row['feature']:<25} {row['importance']:.3f}")
    print()
    
    metrics = {
        'rmse': rmse,
        'mae': mae,
        'r2': r2,
        'within_10': within_10,
        'within_15': within_15,
        'within_20': within_20,
        'within_30': within_30,
        'feature_importance': feature_importance.to_dict('records'),
        'training_date': datetime.now().isoformat(),
        'n_samples_train': len(X_train),
        'n_samples_test': len(X_test)
    }
    
    return model, metrics

def save_model(model, metrics):
    """
    Save trained model and metadata
    
    Args:
        model: Trained model
        metrics: Evaluation metrics
    """
    print("="*70)
    print("SAVING MODEL")
    print("="*70)
    print()
    
    # Create models directory if it doesn't exist
    os.makedirs(os.path.dirname(MODEL_FILE), exist_ok=True)
    
    # Save model
    with open(MODEL_FILE, 'wb') as f:
        pickle.dump(model, f)
    
    print(f"✓ Model saved to: {MODEL_FILE}")
    
    # Save metrics
    metrics_file = MODEL_FILE.replace('.pkl', '_metrics.pkl')
    with open(metrics_file, 'wb') as f:
        pickle.dump(metrics, f)
    
    print(f"✓ Metrics saved to: {metrics_file}")
    print()
    
    # Display file info
    model_size = os.path.getsize(MODEL_FILE) / (1024 * 1024)
    print(f"Model file size: {model_size:.2f} MB")


if __name__ == '__main__':
    print("\n" + "╔" + "═"*68 + "╗")
    print("║" + " "*68 + "║")
    print("║" + " "*15 + "TRAINING MODEL WITH MYSQL DATA" + " "*23 + "║")
    print("║" + " "*68 + "║")
    print("╚" + "═"*68 + "╝\n")
    
    try:
        # Configuration
        print("Configuration:")
        print(f"  Routes: {AVAILABLE_ROUTES}")
        print(f"  Model: Random Forest ({N_ESTIMATORS} estimators)")
        print(f"  Test split: {int(TEST_SIZE*100)}%")
        print()
        
        input("Press Enter to start training...")
        print()
        
        # Step 1: Load data from database
        df = load_training_data_from_db(
            routes=AVAILABLE_ROUTES,  # Use all available routes
            months=None  # Use all available months
        )
        
        # Step 2: Feature engineering
        df = prepare_features(df)
        
        # Step 3: Train model
        model, metrics = train_model(df)
        
        # Step 4: Save model
        save_model(model, metrics)
        
        # Final summary
        print("="*70)
        print("✓ MODEL TRAINING COMPLETE")
        print("="*70)
        print()
        print("Summary:")
        print(f"  Training samples:  {metrics['n_samples_train']:,}")
        print(f"  Test samples:      {metrics['n_samples_test']:,}")
        print(f"  R² Score:          {metrics['r2']:.3f}")
        print(f"  RMSE:              {metrics['rmse']:.2f} passengers")
        print(f"  Accuracy (±15):    {metrics['within_15']:.1f}%")
        print()
        print("Next steps:")
        print("  1. python test_prediction_db.py  (test predictions)")
        print("  2. python api_db.py               (start API server)")
        print("="*70 + "\n")
    
    except Exception as e:
        print(f"\n✗ Training failed: {e}")
        import traceback
        traceback.print_exc()