# Reward Earning System

## Project Description
This project is a reward earning system that allows users to earn rewards based on their activities. It is designed to facilitate user engagement and incentivize behaviors that contribute to the growth of the community.

## Features
- User registration and authentication
- Earning rewards for completed tasks
- Redeeming rewards for various benefits
- Admin dashboard for managing users and rewards
- Reporting and analytics tools

## Tech Stack
- Frontend: React.js
- Backend: Node.js with Express
- Database: MongoDB
- Authentication: JWT
- Deployment: Heroku

## Installation Instructions
1. Clone the repository:
   ```bash
   git clone https://github.com/techmoonverse/reward-earning-system.git
   ```
2. Navigate to the project directory:
   ```bash
   cd reward-earning-system
   ```
3. Install dependencies:
   ```bash
   npm install
   ```
4. Set up environment variables in a .env file.
5. Run the application:
   ```bash
   npm start
   ```

## Database Schema
- **Users**  
  - `id`: ObjectId  
  - `username`: String  
  - `email`: String  
  - `password`: String  
  - `rewards`: Array  
- **Rewards**  
  - `id`: ObjectId  
  - `description`: String  
  - `points`: Number  

## Project Structure
```
reward-earning-system/
├── client/            # Frontend files
├── server/            # Backend files
│   ├── models/        # Database models
│   ├── routes/        # API routes
│   └── controllers/   # Business logic
└── .env               # Environment variables
```

## Security Features
- Password hashing using bcrypt
- JWT for user authentication
- Input validation and sanitization to prevent XSS and SQL injection

## Usage Guide
### For Users:
1. Register to create an account.
2. Complete tasks to earn rewards.
3. Redeem rewards through your user dashboard.

### For Admins:
1. Access the admin dashboard using admin credentials.
2. Manage user accounts and monitor report analytics.
3. Create and manage reward programs.