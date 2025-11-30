P2 Group 22
INF2003 Project [2025/26 T1]

This user manual will include the prerequisites, environment setup, configurations and instructions to run the application. 
By following this user manual, readers shall be able to run the application on both the frontend and backend.

1. Prerequisites:

  Windows 10 or later
  
  MySQL Workbench 8.0 CE
  
  Visual Studio Code with SFTP extension (by Natizyskunk) installed
  
  Any web browser with stable internet connection

3. Environment Setup:

  2.1 MySQL Database Connection:
  
  Connection Method: Standard TCP/IP over SSH
  
  SSH Hostname: 104.198.169.207
  
  SSH username: inf2003-dev
  
  MySQL Hostname: 127.0.0.1
  
  MySQL Server Port: 3306
  
  Username: inf2003-sqldev

  2.2 Visual Studio Code connection to LAMP server via SFTP
  
  Open and Select a new Folder to store the pages
  
  Within Visual Studio Code, Press CTRL + Shift + P to enable the search bar with a >
  
  Type and look for > SFTP: Config
  
  Selecting the option will open up a sftp.json file 
  
  Edit the values of "host","username" and "remotePath" to "104.198.169.207", "inf2003-dev" and     "/var/www/html" respectively
  
  After "openSsh", add two more variables, "interactiveAuth" and "ignore" with the values as "true" and " [".vscode",".git",".DS_Store"]" respectively
  
  Save the sftp.json with the new values
  
  Under the SFTP extension tab on the left, press the Refresh button beside the SFTP: EXPLORER header
  
  A prompt to enter the Password should appear
  
  Navigate back to the Explorer tab (CTRL + Shift + E), right click on a blank section, followed by selecting the "Sync Remote -> Local" Option to retrieve the web application files

4. Predictive Analytics 

  4.1 Prerequisites

    Python 3.8 or later
    Install required packages:
    pip install pandas numpy scikit-learn flask flask-cors

  4.2 Setup

    Step 1: Start SSH Tunnel (keep terminal open)
    ssh -L 33060:127.0.0.1:3306 inf2003-dev@104.198.169.207
    
    Step 2: Start PHP API Server (keep terminal open)
    cd "Database(Predictive analytics)"
    php -S localhost:8000

  4.3 Train the Model

    In a new terminal:
    
    cd "Database(Predictive analytics)"
    python train_model.py
    
    Press Enter when prompted.
    
    This will:
    - Load historical ridership data from MySQL
    - Train a Random Forest model
    - Save model to: models/ridership_model.pkl

  4.4 View Predictions

    Navigate to: http://35.212.180.159/predictive.php
    
    Select a bus route and date to view hourly ridership forecasts.

  4.5 Troubleshooting

    "Model file not found" → Run train_model.py
    "Connection refused" → Verify SSH tunnel and PHP server are running
    "No training data" → Check database connection

    

5. Instructions:

The Environment Setup and Visual Studio Code Connection grants access to the backend database files as well as the frontend web app page files

To run the application, access the web URI click into the link: http://35.212.180.159/



User account logins are as follows:

User account

Username: User1

Email: User1@email.com

Password: User123!

Admin Account:

Username: Admin

Email: admin@yourtrip.com

Password: Admin123!
