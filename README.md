# INF2006 Project – Cloud-Native Financial Document Processor (AIaaS)

> A cloud-native AI-as-a-Service (AIaaS) platform that automates financial document processing (invoices, receipts) through a multi-stage machine learning pipeline. The system performs OCR, entity extraction, validation, auto-categorisation, and Big Data analytics.

## 📖 Table of Contents
* [Architecture Overview](#-architecture-overview)
* [Key Features](#-key-features)
* [Technology Stack](#-technology-stack)
* [Deployment Guide](#-deployment-guide)
* [Running the Application](#-running-the-application)
* [Team & Contributions](#-team--contributions)

---

## 🏗 Architecture Overview

The system is split into two distinct pipelines: Real-Time Operational processing and Batch Analytics.

### 1. Operational Pipeline (Real-Time)
Handles incoming financial documents and performs immediate extraction.
* **Flow:** `API Gateway` → `S3 (Presigned Upload)` → `Step Functions` → `Textract` → `LayoutLM (SageMaker)` → `DynamoDB`
* **Key Components:**
    * **Orchestration:** Lambda & Step Functions.
    * **AI:** Amazon Textract (OCR) & Custom LayoutLM on SageMaker (Entity Extraction).

### 2. Analytics Pipeline (Batch Big Data)
Processes historical data to enable analytics and ML training.
* **Flow:** `DynamoDB Streams` → `Lambda ETL` → `S3 Data Lake` → `EMR Spark` → `Athena` → `QuickSight`
* **Key Components:**
    * **Data Lake:** S3 (JSON Lines/Parquet/Delta optimisation).
    * **Compute:** Apache Spark on EMR for ETL and Random Forest classification.
    * **Visualisation:** Amazon QuickSight.

---

## 🚀 Key Features

### ☁️ Cloud-Native Architecture
* Fully serverless ingestion pipeline (API Gateway, Lambda, Step Functions, EventBridge).
* Robust workflow control with retry logic and fault tolerance.

### 🤖 AI Document Processing
* **OCR:** High-accuracy text detection via Amazon Textract.
* **Extraction:** Entity extraction using a custom LayoutLM model deployed on SageMaker.
* **Storage:** Extracted results persisted in DynamoDB.

### 📊 Big Data Lakehouse
* Decoupled storage and compute using Amazon S3 and EMR.
* Data Lake built with JSON Lines, Parquet, and Delta optimisation.

### 📈 Analytics & Machine Learning
* Batch ETL processing with Apache Spark.
* Spend aggregation and category analytics.
* **ML Prediction:** Random Forest model for category prediction using Spark MLlib.
* **Visualisation:** Automated chart generation (Matplotlib) and interactive dashboards (QuickSight).

### ⚙️ Infrastructure as Code
* Entire system deployed through **Terraform** for reproducibility.

---

## 🛠 Technology Stack

| Category | Technology | Purpose |
| :--- | :--- | :--- |
| **Cloud Provider** | AWS | Infrastructure and managed services. |
| **IaC** | Terraform | Provisioning and configuration. |
| **Orchestration** | Step Functions, Lambda, SQS | Workflow and serverless compute. |
| **AI/ML** | Textract, SageMaker, Spark MLlib | OCR, entity extraction, model training. |
| **Big Data** | Apache Spark (PySpark) | ETL, analytics, ML. |
| **Frontend** | HTML/JS with Node.js proxy | UI and secure API communication. |

---

## 📦 Deployment Guide

### Prerequisites
Ensure you have the following installed and configured:
* **AWS CLI** (Configured with Administrator permissions via `aws configure`).
* **Terraform** (v1.0+).
* **Node.js** (v16+).
* **Python** (3.9).

### Phase 1: Infrastructure Deployment (Terraform)

1. Navigate to the Infrastructure as Code directory:
    ```bash
    cd backend-iac
    ```

2. Initialise and apply Terraform:
    ```bash
    terraform init
    terraform validate
    terraform plan
    terraform apply -auto-approve
    ```

> **Important:** Record the following outputs from the terminal:
> * API Gateway base URL
> * Document Upload S3 Bucket name
> * Analytics Data Lake S3 Bucket name

### Phase 2: Post-Deployment Configuration

#### 1. Upload Spark Scripts to S3
Replace `[DOCS_BUCKET_NAME]` with the bucket name from Phase 1.
```bash
aws s3 cp ../backend-spark/batch_analytics_safe.py s3://[DOCS_BUCKET_NAME]/scripts/
aws s3 cp ../backend-spark/spark_ml_analytics.py s3://[DOCS_BUCKET_NAME]/scripts/
```

#### 2. Configure Backend Proxy
Navigate to the proxy directory:
```bash
cd ../backend-proxy
```

Create a `.env` file with the following content:
```env
PORT=3000
API_GATEWAY_URL=https://[API_ID][.execute-api.us-east-1.amazonaws.com/generate-upload-url](https://.execute-api.us-east-1.amazonaws.com/generate-upload-url)
DASHBOARD_API_URL=https://[API_ID][.execute-api.us-east-1.amazonaws.com/get-dashboard-url](https://.execute-api.us-east-1.amazonaws.com/get-dashboard-url)
```

Start the proxy server:
```bash
npm install
node server.js
```

#### 3. Set Up QuickSight Dashboard
1. Run the AWS Glue Crawler: `inf2006-analytics-crawler`.
2. In **QuickSight**, create a dataset using **Athena**.
3. Select database `inf2006_analytics_db` and table `category_summary_v3`.
4. Build a visual (e.g., Bar Chart) and publish as a dashboard.
5. *Optional:* Update the Dashboard ID in the Lambda environment variables if required.

---

## ▶️ Running the Application

### 1. Ingestion (Upload Documents)
* Open `frontend/index.html`.
* Upload receipts or invoices.
* **Verify:** Check AWS Step Functions console to see the `OCR` → `Entity Extraction` → `Database Save` flow.

### 2. Run Big Data Analytics (EMR)
SSH into your EMR cluster master node:
```bash
ssh -i path/to/project-key.pem hadoop@ec2-xx-xx-xx.compute-1.amazonaws.com
```

Run the ETL Job:
```bash
spark-submit s3://[DOCS_BUCKET_NAME]/scripts/batch_analytics_safe.py
```

### 3. Machine Learning Training
Run the Random Forest training job:
```bash
spark-submit s3://[DOCS_BUCKET_NAME]/scripts/spark_ml_analytics.py
```
*Expected Output:* `Accuracy: 0.96`

---

## 👥 Team & Contributions

| Name | Role | Core Responsibility |
| :--- | :--- | :--- |
| **Max Tan** | Cloud Architect & Front-End Dev | IaC (Terraform), API Gateway, Lambda integration, Big Data Technologies. |
| **Loh Kai Chuin** | Cloud Architect & Front-End Dev | Website Frontend, IaC (Terraform) for Frontend, UI/UX. |
| **Nurul Zahirah** | ML Developer | Model 2: Entity Extraction (LayoutLM), BIO Tagging & Label Alignment. |
| **Linus Koh** | ML Developer | Model 2: Entity Extraction, Model 3: Expense Categorisation. |
| **Wong Li Shen** | ML Engineer | Model Evaluation (Azure vs AWS), OCR Model 1. |
