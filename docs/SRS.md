# Software Requirements Specification (SRS)  
## Recycling Pickup & Resale Web Application  

**Course:** INFO 3135 – Advanced Web Applications  
**Project Type:** Web-based Application (Core PHP)  
**Technology Constraint:** Core PHP (No Frameworks), MySQL, HTML/CSS/JavaScript  

---

## 1. Introduction  

### 1.1 Purpose  
This Software Requirements Specification (SRS) document defines the functional and non-functional requirements for the Recycling Pickup & Resale Web Application.  

The purpose of this document is to clearly describe system behavior, user interactions, and constraints prior to system design and implementation.  

This document serves as a reference for development, testing, and evaluation.  

---

### 1.2 Scope  
The system enables users to list electronics and small appliances for free recycling pickup. Items are collected by drivers, inspected by technicians, repaired if viable, and resold through an internal marketplace.  

Revenue from resale primarily benefits the recycling depot, with a small percentage paid to the original user.  

The system is **not a general marketplace**. Selling is controlled exclusively by the depot after inspection.  

---

### 1.3 Definitions, Acronyms, and Abbreviations  

- **Recycler:** User listing an item for recycling  
- **Depot:** Organization operating the recycling service  
- **Pickup Request:** A scheduled request to collect items from a recycler  
- **Inspection:** Evaluation of item condition by technician  
- **Marketplace Listing:** Item approved and listed for resale  
- **Core PHP:** PHP without frameworks such as Laravel or Symfony  

---

## 2. Overall Description  

### 2.1 Product Perspective  
The system is a standalone web application built using core PHP and MySQL.  

It follows a basic MVC-style separation using PHP includes and directories.  

The application will be accessed through modern web browsers.  

---

### 2.2 Product Functions  

- User registration and authentication  
- Recycling item listing and pickup requests  
- Pickup assignment and completion  
- Item inspection and condition tracking  
- Marketplace listing and purchase handling  
- Revenue calculation and payout tracking  

---

### 2.3 User Classes and Characteristics  

| User Class | Description |
|----------|------------|
| Recycler | General public users listing items for recycling |
| Driver | Staff responsible for collecting items |
| Technician | Staff inspecting and repairing items |
| Admin | System administrators managing operations |
| Buyer | Users purchasing repaired items |

---

### 2.4 Operating Environment  

- **Server:** Apache / Nginx  
- **Backend:** PHP 8.x (Core PHP)  
- **Database:** MySQL  
- **Client:** Modern web browsers (Chrome, Firefox, Edge)  

---

### 2.5 Design and Implementation Constraints  

- No PHP frameworks allowed  
- Role-based access control required  
- Must support mobile-friendly layouts  
- Must follow academic integrity and course guidelines  

---

### 2.6 Assumptions and Dependencies  

- Users have internet access  
- Payment handling may be simulated or simplified  
- Email/SMS notifications may be mocked or basic  

---

## 3. System Requirements  

### 3.1 Functional Requirements  

#### 3.1.1 User Authentication  

- **FR-1:** The system shall allow users to register with email and password  
- **FR-2:** The system shall authenticate users using hashed passwords  
- **FR-3:** The system shall assign roles to users upon registration or by admin  

---

#### 3.1.2 Recycling Listings  

- **FR-4:** The system shall allow recyclers to create item listings  
- **FR-5:** Listings shall include category, description, photos, and pickup address  
- **FR-6:** The system shall allow users to request a pickup for listed items  

---

#### 3.1.3 Pickup Management  

- **FR-7:** The system shall allow admins to assign pickups to drivers  
- **FR-8:** Drivers shall mark pickups as completed  
- **FR-9:** Pickup status shall be updated in real time  

---

#### 3.1.4 Inspection and Repair  

- **FR-10:** Technicians shall record inspection results  
- **FR-11:** Items shall be marked as working, repairable, or non-repairable  
- **FR-12:** Technicians shall approve items for marketplace listing  

---

#### 3.1.5 Marketplace  

- **FR-13:** The system shall allow admins to create marketplace listings  
- **FR-14:** Buyers shall browse and purchase items  
- **FR-15:** Orders shall be recorded in the system  

---

#### 3.1.6 Revenue and Payout  

- **FR-16:** The system shall calculate revenue split  
- **FR-17:** The system shall record recycler payouts  

---

### 3.2 Non-Functional Requirements  

| Category | Requirement |
|---------|------------|
| Security | Password hashing, role-based access |
| Privacy | Minimal personal data storage |
| Performance | Page load under normal limits |
| Usability | Mobile-friendly UI |
| Reliability | Accurate state transitions |
| Maintainability | Modular PHP structure |
| Auditability | Status and transaction logging |

---

## 4. External Interface Requirements  

### 4.1 User Interfaces  

- Web-based graphical interface  
- Responsive layout for mobile devices  
- Separate dashboards per user role  

---

### 4.2 Hardware Interfaces  

- None (standard web hosting environment)  

---

### 4.3 Software Interfaces  

- PHP–MySQL database interface  
- Optional email service for notifications  

---

## 5. System Workflow (High-Level)  

1. Recycler creates item listing  
2. Recycler requests pickup  
3. Admin assigns pickup  
4. Driver completes pickup  
5. Technician inspects item  
6. Admin lists item for sale if approved  
7. Buyer purchases item  
8. Revenue split is recorded  

---

## 6. Project Plan & Milestones  

| Phase | Description |
|------|------------|
| Week 1 | Requirements & Use Case Design |
| Week 2 | Database Design (ERD) |
| Week 3 | Core PHP Authentication |
| Week 4 | Recycling Listings & Pickups |
| Week 5 | Inspection & Marketplace |
| Week 6 | Testing & Refinement |
| Week 7 | Final Presentation & Submission |

---

## 7. Future Enhancements  

- Real payment gateway integration  
- Route optimization for drivers  
- Analytics dashboard for depot  
- Expanded categories beyond electronics  

---

## 8. Approval  

This SRS document represents the agreed-upon requirements for the Recycling Pickup & Resale Web Application and will serve as the baseline for future development.  

---

**End of Document**
