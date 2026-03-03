
# SpendLess

## Requirements

- PHP >= 8.2
- Composer
- Node.js & npm
- MySQL or compatible database

## Installation

1. **Clone the repository:**
	```bash
	git clone git@github.com:TheFatPanda-Dev/spendless.git
	cd spendless
	```

2. **Install PHP dependencies:**
	```bash
	composer install
	```

3. **Install Node dependencies:**
	```bash
	npm install
	```

4. **Copy .env file and set up database:**
	```bash
	cp .env.example .env
	# Edit .env and set your DB_DATABASE, DB_USERNAME, DB_PASSWORD
	```

5. **Generate application key:**
	```bash
	php artisan key:generate
	```

6. **Run migrations:**
	```bash
	php artisan migrate
	```

7. **Start the development server:**
	```bash
	npm run dev
	```

---
For more details, see the Laravel and React documentation.
