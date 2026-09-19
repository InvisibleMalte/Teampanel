# Teampanel
I started creating this Team Management Panel for German Minecraft servers in June 2026 as a side project.  
Feel free to give feedback, share ideas, or contribute to this project.

## Requirements

- Apache/nginx web server
- PHP
- MySQL database

## Installation
First, download the project as a .zip file and extract it on your web server.  
Then copy the "teampanel_template.sql" file, import it into your MySQL database, and create a database account for the web server.  
Enter the login credentials for this account into the fields in the "config.json" file and save the file.

Now everything is ready to connect to the Teampanel website. Go to the website and log in with the setup account "admin" and the team token "admin123". Then create your own account (Leitung -> Teammitglieder -> Neues Mitglied) with administrator privileges (Leitung -> Teammitglieder -> "Akte" on your account -> Administrator -> activate), delete the default setup account, and create accounts for your team members.

I recommend using passwords for all Leader or Administrator accounts, since every Leader or Administrator can see the team token of every team member.

## Functions
Currently, this panel is designed for managing the server team of a German Minecraft server.