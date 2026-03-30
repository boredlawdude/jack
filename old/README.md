# Contracts App

A PHP MVC application for managing contracts, companies, people, and related documents.

## Features
- Contract and document management (upload, view, download)
- Company and people management
- Role-based access (admin, superuser, etc.)
- Department and contract template management
- System settings via admin UI

## Requirements
- PHP 8.1+
- MySQL 5.7+/8.x
- Composer (for vendor autoloading)
- Apache/Nginx (or PHP built-in server for dev)

## Setup Instructions

1. **Clone the repository:**
   ```sh
   git clone https://github.com/yourusername/contracts_app.git
   cd contracts_app
   ```

2. **Install dependencies:**
   ```sh
   composer install
   ```

3. **Copy and configure your environment:**
   ```sh
   cp .env.example .env
   # Edit .env with your DB credentials and settings
   ```

4. **Set up the database:**
   - Create a MySQL database (e.g., `contracts_app`)
   - Import the schema:
     ```sh
     mysql -u youruser -p contracts_app < contract_manager_schema.sql
     # Optionally import sample data
     # mysql -u youruser -p contracts_app < contract_manager_full_backup.sql
     ```

5. **Set permissions for storage:**
   ```sh
   mkdir -p storage/generated_docs
   chmod -R 775 storage
   ln -s ../storage public/storage
   ```

6. **Run the app locally:**
   - With PHP built-in server:
     ```sh
     php -S localhost:8080 -t public
     ```
   - Or configure Apache/Nginx to point to the `public/` directory.

7. **Login:**
   - Default admin credentials are set in your seed data or as configured.

## Notes
- All uploaded documents and exhibits are stored in the `storage/` directory.
- System settings are managed via the admin UI (`/index.php?page=admin_settings`).
- For production, secure your `.env` and `storage/` directories.

## Contributing
Pull requests and issues are welcome!

## License
MIT (or your chosen license)
