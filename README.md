# 🌿 Dayflow – Human Resource Management System (HRMS)

> **Every workday, perfectly aligned.**

Dayflow is a web-based **Human Resource Management System (HRMS)** designed to digitize and streamline essential HR operations such as employee onboarding, attendance tracking, leave management, and payroll visibility. The system supports role-based access for **Admins/HR Officers** and **Employees**, ensuring secure and efficient workforce management.

---

## 📌 Project Overview

Manual HR processes are time-consuming and prone to errors. **Dayflow** provides a centralized digital platform to manage employee data, approvals, attendance, and payroll efficiently.

---

## 🎯 Key Features

### 🔐 Authentication & Authorization
- Secure Sign Up / Sign In
- Email verification
- Role-based access (Admin/HR vs Employee)
- Password security rules

---

### 🏠 Dashboards

#### 👤 Employee Dashboard
- Profile overview
- Attendance tracking
- Leave requests
- Recent activity & alerts

#### 🛠️ Admin / HR Dashboard
- Employee management
- Attendance records
- Leave approvals
- Payroll overview
- Ability to switch between employees

---

### 👨‍💼 Employee Profile Management
- View personal & job details
- Salary structure (read-only for employees)
- Profile picture & documents
- Admin can edit all employee details

---

### ⏱️ Attendance Management
- Daily & weekly attendance views
- Check-in / Check-out functionality
- Attendance status:
  - Present
  - Absent
  - Half-day
  - Leave
- Role-based visibility

---

### 🌴 Leave & Time-Off Management
- Apply for Paid, Sick, or Unpaid leave
- Select date range & add remarks
- Leave status:
  - Pending
  - Approved
  - Rejected
- Admin/HR approval with comments

---

### 💰 Payroll Management
- Employees can view payroll (read-only)
- Admin can:
  - Update salary structure
  - Ensure payroll accuracy
  - View payroll of all employees

---

## 🛠️ Technology Stack

- **Frontend:** HTML, CSS, Bootstrap, JavaScript, jQuery  
- **Backend:** PHP  
- **Database:** MySQL  
- **Architecture:** Role-based modular structure  

---

## 📁 Project Structure

```plaintext
hrms/
│
├── config/
│   ├── database.php
│   └── constants.php
│
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── functions.php
│
├── auth/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   └── authenticate.php
│
├── admin/
│   ├── index.php
│   ├── employees.php
│   ├── attendance.php
│   ├── leave-requests.php
│   ├── reports.php
│   └── settings.php
│
├── employee/
│   ├── index.php
│   ├── profile.php
│   ├── attendance.php
│   ├── leave-request.php
│   └── leave-history.php
│
├── api/
│   ├── attendance.php
│   ├── leave.php
│   └── profile.php
│
├── assets/
│   ├── css/
│   ├── js/
│   └── img/
│
├── uploads/
│   └── profile_pictures/
│
├── database/
│   └── hrms.sql
│
└── index.php
