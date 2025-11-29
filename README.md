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
  
  SSH Hostname: 35.212.180.159
  
  SSH username: inf2003-dev
  
  MySQL Hostname: 127.0.0.1
  
  MySQL Server Port: 3306
  
  Username: inf2003-sqldev

  2.2 Visual Studio Code connection to LAMP server via SFTP
  
  Open and Select a new Folder to store the pages
  
  Within Visual Studio Code, Press CTRL + Shift + P to enable the search bar with a >
  
  Type and look for > SFTP: Config
  
  Selecting the option will open up a sftp.json file 
  
  Edit the values of "host","username" and "remotePath" to "35.212.180.159", "inf2003-dev" and     "/var/www/html" respectively
  
  After "openSsh", add two more variables, "interactiveAuth" and "ignore" with the values as "true" and " [".vscode",".git",".DS_Store"]" respectively
  
  Save the sftp.json with the new values
  
  Under the SFTP extension tab on the left, press the Refresh button beside the SFTP: EXPLORER header
  
  A prompt to enter the Password should appear
  
  Navigate back to the Explorer tab (CTRL + Shift + E), right click on a blank section, followed by selecting the "Sync Remote -> Local" Option to retrieve the web application files
    

Instructions:
