# SocialSocial

A simple social media platform built with PHP, SQLite, and HTML/CSS.

## Features

- User registration and login
- Post creation, liking, and commenting
- Follow/unfollow users
- Private messaging
- Profile management (password)
- Search for users and posts
- Trending posts based on engagement
- Responsive design

## Setup

To start this Social-Platform you have to:
1. Download XAMPP (Local-Server for PHP)
2. Copy this Repository into the C:\xampp\htdocs
3. Start the Apache server at XAMPP
4. Open the Path local: http://localhost/SocialSocial/index.php

## Database

The application uses SQLite. The database file `social.db` is created automatically.

Tables:
- users: id, username, password, avatar
- posts: id, user_id, content, created_at
- comments: id, post_id, user_id, content, created_at
- follows: follower_id, following_id
- likes: user_id, post_id
- messages: id, sender_id, receiver_id, content, created_at, post_id
- emails: id, email
- notifications: id, user_id, type, from_user_id, post_id, message, is_read, created_at

## Security

- Passwords are hashed with PASSWORD_DEFAULT
- CSRF tokens are used for form submissions
- Input is sanitized with htmlspecialchars
- Prepared statements prevent SQL injection
