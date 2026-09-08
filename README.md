# MyPocket 💰

**MyPocket** is a personal finance management system developed in **PHP** using **Object-Oriented Programming (OOP)** principles, with full **CRUD** operations and persistent storage in **MySQL via PDO**. The project simulates the back-end of a personal finance application, allowing users to create, view, edit, and delete income and expense transactions, with automatic balance calculation.

The application was designed to reinforce core OOP concepts such as **encapsulation, inheritance, abstraction, and polymorphism**, combined with the **Repository pattern** for database access, ensuring data integrity and a well-structured architecture.

## Features

- **Create**: register income and expenses;
- **Read**: display a complete transaction history, most recent first;
- **Update**: edit an existing transaction (type, value, date, description);
- **Delete**: remove a transaction;
- Automatically calculate the wallet balance directly from the database;
- Prevent negative balances through validation before saving or updating;
- Block a transaction from becoming an expense that would zero out or exceed the available balance;
- Identify transactions as income (Entrada) or expense (Saída);
- Feedback messages (success/error) via PHP sessions;
- Responsive interface built with Bootstrap.

## Concepts Applied

- Object-Oriented Programming (OOP);
- Abstract classes (`Transacao`);
- Inheritance (`Receita`, `Despesa`);
- Encapsulation (protected properties with getters);
- Polymorphism (`getTipo()`);
- Repository pattern for data persistence (`TransacaoRepo`);
- Full CRUD via PDO with prepared statements;
- Strict typing (`declare(strict_types=1)`);
- Exception handling (`try/catch`).

## Project Structure

```text
MyPocketCRUD/
├── classes/
│   ├── Transacao.php      # abstract base class
│   ├── Receita.php        # income
│   └── Despesa.php        # expense
├── database/
│   ├── conexao.php        # PDO connection
│   ├── TransacaoRepo.php  # CRUD + balance calculation
│   ├── editar.php         # Update
│   └── delete.php         # Delete
├── img/
├── index.php               # dashboard: balance, form (Create), statement (Read)
├── processa.php             # handles new transaction submission
└── schema.txt               # database schema (DDL)
```

## Technologies Used

- PHP 8.1+
- MySQL
- PDO (PHP Data Objects)
- HTML5
- Bootstrap 5
- Git & GitHub

## Setup

1. Create the database and table by running the SQL in `schema.txt`;
2. Set your database credentials in `database/conexao.php` (host, database name, user, password);
3. Serve the project with a local PHP server (e.g. `php -S localhost:8000`) or via XAMPP/WAMP.

---

Project developed for the **Web Programming II** course, focusing on software design, code organization, and the practical application of OOP and CRUD operations with database persistence in PHP.
