# cPanel Deployment Guide: Fisch Analytics

Because `git clone` only brings your tracked code over, we need to set up the environment, dependencies, and database on the new server.

## Step 1: Create the `.env` File
Your `.env` file contains sensitive passwords and was ignored by Git. We need to create a new one on the server.
1. Open the **cPanel File Manager** and go to `public_html`.
2. Find the file named `.env.example`.
3. Rename this file to exactly `.env` (or copy it).
4. Select `.env` and click **Edit**.
5. Update your database credentials to match your cPanel MySQL database:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_cpanel_database_name
   DB_USERNAME=your_cpanel_db_username
   DB_PASSWORD=your_cpanel_db_password
   ```
6. Save and close the file.

## Step 2: Install Dependencies via Terminal
We need to install the PHP packages that Laravel runs on.
1. Go to your cPanel dashboard and open the **Terminal** tool.
2. Navigate to your app directory by typing: 
   `cd public_html`
3. Install the required packages without dev dependencies: 
   `composer install --optimize-autoloader --no-dev`
4. Generate the application's unique encryption key: 
   `php artisan key:generate`

## Step 3: Run Database Migrations
Your database is currently empty. We need to build the tables and seed the Fishing Rods.
1. While still inside the **Terminal** in `public_html`, run this command:
   `php artisan migrate:fresh --seed --seeder=MasterRodSeeder`
   *(This will create the `tracked_players`, `players`, `player_rods`, etc. tables and seed all 227 Master Rod images).*

## Step 4: Fix Folder Permissions
Laravel needs permission to write log files and cache on your shared server.
1. Open the cPanel **File Manager**.
2. Navigate inside `public_html`.
3. Right-click the `storage/` folder and click **Change Permissions**. Set it to `755` (or `775` if your server uses strict groups).
4. Right-click the `bootstrap/cache/` folder and set its permissions to `755` (or `775`).

## Step 5: Test the Application
1. Go to your domain name in the browser (e.g. `https://yourwebsite.com`).
2. Your newly added `.htaccess` file should automatically redirect the traffic into your Laravel application.
3. Because the database was reset on the live server, you will need to go to `/register` once more to create your admin account and add the Roblox user you wish to track!
