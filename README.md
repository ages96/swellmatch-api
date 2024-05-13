# Swellmatch API

## Framework

- PHP >= 5.6.4
- Laravel Lumen Framework 5.4.*

## Installation Steps

1. Create a MySQL database named `db_swellmatch`.
2. Copy `.env.example` to `.env` in the project root directory.
3. Adjust the `database` section in the `.env` file with your MySQL database credentials.
4. Run `composer install` to install the dependencies.
5. Run `php artisan migrate` to migrate the database schema.
6. (Optional) Run `php artisan db:seed` to seed the database with sample data.
7. Run `php artisan serve` to start the development server.

Alternatively, you can use PHP's built-in server:

## Postman Documentation

Explore the API endpoints and test requests using the [Postman Documentation](https://documenter.getpostman.com/view/10341950/2sA3JNa11S).

## Notes

- Make sure PHP and Composer are installed on your system.
- This API is built using Laravel Lumen framework. Refer to Laravel Lumen documentation for more information on development and deployment.