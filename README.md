# 💬 PHP Discussion Forum

PHP Discussion Forum is a web-based application that enables users to ask questions, post answers, and participate in discussions through replies. The project focuses on building a structured, database-driven forum using PHP and MySQL with a responsive user interface.

---

## 🚀 Features
- ✅ **User Authentication** – Secure user registration, login, and logout
- ✅ **Question & Answer System** – Users can post questions and submit answers
- ✅ **Reply System** – Participate in discussions through replies
- ✅ **Category-Based Discussions** – Organize questions under different categories
- ✅ **User Profile Management** – View and update user profiles
- ✅ **Admin Panel** – Manage users, posts, and categories
- ✅ **Password Reset** – Secure password recovery functionality
- ✅ **File Upload Support** – Upload profile images or related files
- ✅ **Responsive UI** – Built using Bootstrap for better user experience

---

## 🛠️ Tech Stack
- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML5, CSS3, Bootstrap
- **Scripting:** JavaScript, jQuery
- **Server:** Apache (XAMPP)

---

## 📂 Project Structure

```text
/qanda
│── app/
│── uploads/
│── admin_*.php
│── db.php
│── header.php
│── footer.php
│── style.css
│── dashboard.php
│── login.php
│── register.php
│── post_question.php
│── post_answer.php
I see exactly what is happening now, and you are right—it is not rendering correctly in your screenshot.

The problem is hidden empty spaces at the start of your lines.

The Problem: Indentation
If you look closely at your screenshot, there is a small gap (empty space) between the line numbers and your text.

In Markdown: If you add spaces at the start of a line, GitHub thinks, "Oh, this is code! I should show it exactly as it is and not render it."

Because your text has spaces at the front, GitHub is turning off the formatting.

The Fix: Remove the Spaces
You need to delete the empty space on the left so every line touches the very beginning of the row.

Do this:

Go back to Edit mode (click the pencil ✏️).

Select everything (Ctrl+A).

Hold Shift and press Tab a few times to move all text to the left.

OR: Delete everything and copy this version below. I have stripped all the hidden spaces for you.

Markdown

# 💬 PHP Discussion Forum

PHP Discussion Forum is a web-based application that enables users to ask questions, post answers, and participate in discussions through replies. The project focuses on building a structured, database-driven forum using PHP and MySQL with a responsive user interface.

---

## 🚀 Features
- ✅ **User Authentication** – Secure user registration, login, and logout
- ✅ **Question & Answer System** – Users can post questions and submit answers
- ✅ **Reply System** – Participate in discussions through replies
- ✅ **Category-Based Discussions** – Organize questions under different categories
- ✅ **User Profile Management** – View and update user profiles
- ✅ **Admin Panel** – Manage users, posts, and categories
- ✅ **Password Reset** – Secure password recovery functionality
- ✅ **File Upload Support** – Upload profile images or related files
- ✅ **Responsive UI** – Built using Bootstrap for better user experience

---

## 🛠️ Tech Stack
- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML5, CSS3, Bootstrap
- **Scripting:** JavaScript, jQuery
- **Server:** Apache (XAMPP)

---

## 📂 Project Structure

```text
/qanda
│── app/
│── uploads/
│── admin_*.php
│── db.php
│── header.php
│── footer.php
│── style.css
│── dashboard.php
│── login.php
│── register.php
│── post_question.php
│── post_answer.php
🚀 Setup Instructions
Install XAMPP on your system.

Start Apache and MySQL from the XAMPP Control Panel.

Clone the repository:

Bash

git clone [https://github.com/Krithik0908/php-discussion-forum.git](https://github.com/Krithik0908/php-discussion-forum.git)
Move the project folder to:

Plaintext

C:\xampp\htdocs\qanda
Open phpMyAdmin and create a new MySQL database.

Import the database file (if provided).

Update database credentials in db.php.

Open a browser and navigate to:

Plaintext

http://localhost/qanda
📘 Learning Outcomes
Practical experience with PHP and MySQL

Understanding of CRUD operations

Implementation of authentication systems

Frontend integration using Bootstrap and jQuery

Hands-on experience with Git and GitHub

👨‍💻 Author
Krithik
