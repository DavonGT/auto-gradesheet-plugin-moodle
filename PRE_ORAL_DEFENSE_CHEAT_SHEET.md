#  PRE-ORAL DEFENSE MASTER CHEAT SHEET (CHAPTERS 1–3)
**Thesis Title:** Automated Grade Sheet Generation Plugin for the Moodle Learning Management System for Faculty Grade Reporting  
**Locale:** College of Computer Studies (CCS), Eastern Samar State University (ESSU) Main Campus  
**Target Users / Sample Size:** N = 3 Faculty Members (Complete Enumeration / Total Population)  
**Research Design:** Developmental-Descriptive | **SDLC:** Prototyping Model (Sommerville, 2015)  
**Evaluation Standard:** ISO/IEC 25010 Software Quality Model (Functional Suitability, Usability, Performance Efficiency)  

---

##  QUICK-GLANCE SUMMARY TABLE (THE 1-PAGE SUMMARY)

| Chapter | Core Focus | Foundational Concepts & Citations | The #1 Trap Question | Your Winning Defense Keyword |
| :--- | :--- | :--- | :--- | :--- |
| **Ch. 1: Introduction** | The Problem, Objectives, Scope (N = 3) | • Baseline survey: 6–10 hrs wasted on CSV reformatting<br>• Clerical errors & encoding fatigue | *"Why not just use an Excel Macro? Why build a plugin?"* | **"Internal LMS Automation & Zero Data Tampering"** (Data stays inside Moodle; eliminates offline copy-paste risks). |
| **Ch. 2: RRL / Studies** | Theoretical Justification & Evidence | • **TAM (Davis, 1989):** Perceived Usefulness & Ease of Use<br>• **Transmutation (Guskey, 2015):** 1.0–5.0 scaling<br>• **Prototyping (Sommerville, 2015):** Localized UI/report fit | *"Davis (1989) is 35 years old! Why use an outdated citation?"* | **"Seminal/Foundational Theory"** paired with contemporary studies (Henderson, 2017; Moodle, 2025). |
| **Ch. 3: Methodology** | Architecture, SDLC, Database, Testing | • **Thin-Controller & Thick-Service Pattern**<br>• **Moodle XMLDB** (5 custom tables)<br>• **Descriptive Statistics** (Mean & %) | *"Can instructors edit grades directly inside your plugin?"* | **"Single Source of Truth"** (Raw grades stay in Moodle Gradebook; plugin only computes and maps). |

---

##  CHAPTER 1: THE FOUNDATION & MOTIVATION

### 1. The Core Problem Statement
* **The Current Reality:** Moodle is used for quizzes, exams, and attendance, but its native grade export is a **raw CSV file**.
* **The Operational Friction:** Instructors must manually open Excel, copy-paste rows, split columns, manually calculate percentage formulas, apply transmutation, and format everything to match the Registrar's paper layout.
* **The Empirical Proof (Your Baseline Survey):**
  - **100%** of respondents currently copy-paste from raw CSV into Excel.
  - **66.67%** spend **6 to 10 hours per semester** purely on data cleaning and reformatting.
  - Baseline composite pain rating: **4.89 / 5.00 (Strongly Agree)** regarding encoding fatigue and clerical vulnerability.

### 2. General & Specific Objectives
* **General:** Design, develop, and evaluate an Automated Grade Sheet Generation Plugin for Moodle for faculty grade reporting in CCS, ESSU Main Campus.
* **Specific Objective 1:** Determine system requirements and architectural design needed for the plugin.
* **Specific Objective 2:** Develop the plugin for seamless integration in terms of:
  * *(a)* Data retrieval from Moodle gradebook,
  * *(b)* Grade computation, transmutation, and student status management, and
  * *(c)* Registrar-aligned report formatting and export (PDF & Excel).
* **Specific Objective 3:** Evaluate user acceptability based on **ISO/IEC 25010** software quality characteristics:
  * *(a)* Functional Suitability,
  * *(b)* Usability, and
  * *(c)* Performance Efficiency.

---

### 3. Top Chapter 1 Trap Questions & Verbatim Answers

####  Q1.1: "Why build a Moodle plugin? Why not just give teachers an Excel Macro or Google Sheets template?"
> **Verbatim Answer:**  
> *"An Excel macro or Google Sheet still forces an offline, disconnected workflow. Instructors must repeatedly export CSV files, move sensitive grade data to personal computers, and handle manual file transfers where formulas can accidentally break or rows can shift. Our plugin automates the entire process directly inside Moodle using its native Gradebook API. The data never leaves the secure LMS, eliminating copy-paste errors, broken formulas, and encoding fatigue entirely."*

####  Q1.2: "Your sample size is only 3 faculty members (N = 3). Isn't that too small for a thesis?"
> **Verbatim Answer:**  
> *"Our sampling technique is **Complete Enumeration (Total Population Sampling)**. In the College of Computer Studies at ESSU Main Campus, exactly three faculty members actively utilize the Moodle LMS for student grading in their workflow. We did not sample a tiny fraction of a large group; we captured **100% of the active user population** for this specific institutional process. Furthermore, Nielsen’s usability engineering principles establish that 3 to 5 domain-expert evaluators interacting with a focused interface discover over 80% of functional and usability issues."*

####  Q1.3: "Does the University Registrar officially accept this generated format?"
> **Verbatim Answer:**  
> *"Our plugin does not alter university grading policy; it produces a **Registrar-Aligned Grade Sheet** that programmatically replicates the exact required structure, fields, transmutation scale, and signatory blocks approved by the institution. Furthermore, to prevent future obsolescence if formatting requirements change, category percentage weights and course metadata are dynamically configurable via our custom configuration tables."*

####  Q1.4: "How does your system comply with the Data Privacy Act of 2012 (RA 10173)?"
> **Verbatim Answer:**  
> *"The plugin strictly inherits Moodle’s native **Role-Based Access Control (RBAC)** and capability system (`access.php`). Only authenticated instructors with the `editingteacher` role for that specific course can access the configuration, grading tables, and export functions. Student data is never exposed to third-party APIs or outside servers, and students only have read-only access to their own individualized grade cards."*

---

##  CHAPTER 2: THE THEORETICAL BACKBONE & LITERATURE

### 1. Plain-English Map of Your Citations
* **Coates et al. (2005) & Lonn and Teasley (2009):** LMSs centralize materials, but faculty often preserve disconnected manual routines for administrative reporting.
* **Beatty and Ulasewicz (2006) & Costa et al. (2012):** Faculty only adopt LMS tools when they solve authentic, practical operational tasks rather than providing abstract tech features.
* **Henderson et al. (2017):** Faculty value digital technologies that are time-saving and alleviate administrative burden.
* **Davis (1989) - Technology Acceptance Model (TAM):** The core theory. Acceptance is determined by **Perceived Usefulness (PU)** (saving 6–10 hours) and **Perceived Ease of Use (PEOU)** (one-click generation).
* **Guskey (2015) & Bates (2015):** Grade Transmutation converts raw scores into institutional ordinal scales (1.0 - 5.0), and systems must handle non-numeric states (*"Incomplete"*, *"Dropped"*).
* **Welling and Thomson (2016):** Automated, server-side document generation (PDF/Excel) directly from web databases.
* **Sommerville (2015):** The **Prototyping Model** is ideal when building systems shaped around strict user-interface layouts and localized document outputs.
* **ISO/IEC 25010 (2011) / Miguel et al. (2014):** International standard to systematically evaluate software quality (Functional Suitability, Usability, Performance Efficiency).

---

### 2. Top Chapter 2 Trap Questions & Verbatim Answers

####  Q2.1: "What is the Theoretical or Conceptual Framework of your study?"
> **Verbatim Answer:**  
> *"Our study is anchored on two frameworks:  
> 1. **Davis’s (1989) Technology Acceptance Model (TAM)** as our theoretical foundation—which posits that adoption is driven by **Perceived Usefulness** (time saved eliminating manual data cleaning) and **Perceived Ease of Use** (intuitive, one-click interface).  
> 2. **The ISO/IEC 25010 Software Quality Model** as our evaluation framework—specifically measuring Functional Suitability, Usability, and Performance Efficiency."*

####  Q2.2: "Davis (1989) is over 35 years old! Why cite an old theory in a computer thesis?"
> **Verbatim Answer:**  
> *"Davis (1989) is the **seminal (foundational) literature** that established the Technology Acceptance Model. Foundational theories in software adoption do not expire. To ensure modern relevance, we paired Davis with contemporary studies such as Henderson et al. (2017) and recent Moodle research (2025) to prove that perceived usefulness and ease of use remain active and directly applicable to faculty workflows today."*

####  Q2.3: "Why didn't you just use existing Moodle plugins from moodle.org?"
> **Verbatim Answer:**  
> *"As observed by Costa et al. (2012), generic LMS extensions fail to resolve localized institutional workflows. Existing Moodle export plugins (like Configurable Reports or native CSV export) only dump raw, linear score columns. They do not support ESSU’s specific grading logic: separating Midterms and Finals, custom lecture/lab category weightings, automated transmutation into the Philippine 1.0–5.0 grading scale, non-numeric status overrides (INC, DRP), and generating the exact registrar-compliant layout with official signatory blocks."*

####  Q2.4: "Why did you synthesize literature thematically instead of having separate Local and Foreign Literature sections?"
> **Verbatim Answer:**  
> *"We structured Chapter 2 thematically according to modern Software Engineering literature standards—grouping research around core technical pillars: LMS Adoption Barriers, Grade Transmutation, Automated Document Generation, SDLC Prototyping, and ISO Quality Evaluation. This provides a direct, logical bridge from theory to our technical implementation in Chapter 3."*

---

##  CHAPTER 3: METHODOLOGY & TECHNICAL ARCHITECTURE

### 1. System Architecture & The 5 Custom Database Tables
* **Architectural Pattern:** **Thin-Controller & Thick-Service Modular Pattern**.
  * *Thin Controller (`view.php`, `export.php`):* Handles user input, session authentication, and renders HTML/PDF/Excel.
  * *Thick Service (`classes/service/` / `locallib.php`):* Centralizes all data retrieval, weighting math, transmutation calculations, and validation rules.
  * *Why this matters:* **Single Source of Truth.** On-screen previews, PDF exports, and Excel spreadsheets always call the exact same calculation service. They will never display different grades!
* **Database Management:** Uses Moodle’s database abstraction layer (`$DB`) and **XMLDB schema definitions** (`db/install.xml`):
  1. `local_gradesheet_config`: Stores course report metadata, terms, and authorized institutional signatories.
  2. `local_gradesheet_categories`: Stores dynamic category definitions, display names, and assigned percentage weights.
  3. `local_gradesheet_itemmap`: Junction table linking native Moodle `grade_items` to specific grading periods (Midterm/Final).
  4. `local_gradesheet_transmute`: Defines transmutation boundary brackets (e.g., 95–100% = 1.0, 75–79% = 3.0).
  5. `local_gradesheet_status`: Tracks student-specific non-numeric academic status overrides (*Incomplete*, *Dropped*, *Withdrawn*).

### 2. The 3 Computation Fail-Safes (Flowchart Logic)
1. **Weight Validation (100% Rule):** The system checks whether category weights equal exactly 100%. If sum of weights != 100%, the export and print buttons are **intentionally locked** and a warning is displayed.
2. **Unmapped Items Safety Banner:** Activities in Moodle not assigned to any category are excluded from calculations and flagged with a warning banner to prevent artificial grade distortion.
3. **Status Overrides:** Flags for "Incomplete" or "Dropped" take precedence over mathematical calculations to ensure accurate administrative reporting.

---

### 3. Top Chapter 3 Trap Questions & Verbatim Answers

####  Q3.1: "Can an instructor edit student raw grades directly inside your plugin interface?"
> **Verbatim Answer:**  
> *"**No**, instructors cannot alter raw activity scores inside the plugin. To maintain Moodle as the **Single Source of Truth**, all assignment, quiz, and exam scores must be updated in Moodle’s native gradebook. The plugin automatically fetches and recalculates the updated data. The plugin only allows configuring metadata, category weights, and official institutional status overrides (INC / DRP)."*

#### ❓ Q3.2: "Why didn't you use inferential statistics (T-test, ANOVA, or Pearson r) in your Data Analysis?"
> **Verbatim Answer:**  
> *"Inferential statistics are designed to estimate population parameters from a sample. Because our study utilized **Complete Enumeration (Total Population Sampling, N = 3)**, we evaluated 100% of the target user population in the locale. In total population studies, descriptive statistics (weighted mean, frequency, percentage) provide exact parametric measurements of the group. Applying inferential tests to an N = 3 total population is statistically invalid."*

#### ❓ Q3.3: "Was your evaluation questionnaire validated? Did you compute Cronbach’s Alpha?"
> **Verbatim Answer:**  
> *"Our evaluation questionnaire was adapted directly from the international **ISO/IEC 25010 Software Quality Model**. Content and face validation were conducted by IT faculty experts and our adviser using an evaluation checklist to ensure construct validity. Because our sample size is N = 3, internal consistency metrics like Cronbach’s Alpha are mathematically volatile; expert content validation is the recognized standard for domain-specific evaluations with total population sampling."*

#### ❓ Q3.4: "How did you test the system for 2,500 students if your actual classes have only 50 students?"
> **Verbatim Answer:**  
> *"We conducted stress-testing using backend Command Line Interface (CLI) scripts and an assertion-based test harness that generated synthetic cohorts of up to 2,500 students. This tested server memory limits, database query execution times, and PDF rendering (TCPDF) under extreme loads to verify the ISO/IEC 25010 **Performance Efficiency** requirement, ensuring zero timeouts during university-wide peak grading deadlines."*

---

## 🎯 DEFENSE DAY SURVIVAL GUIDE (THE DO'S & DON'TS)

### 👥 Team Role Delegation
* **Speaker 1 (The Problem & Context):** Presents Background, Baseline Pain Points (Table 4.1/4.2 data), and Objectives.
* **Speaker 2 (The Theory & Architecture):** Presents Literature justification, SDLC Prototyping, and Thin-Controller/Thick-Service design.
* **Speaker 3 (The Methodologist & Evaluation):** Presents Scope, N = 3 Complete Enumeration defense, XMLDB schema, and ISO/IEC 25010 testing.

### 💡 5 Golden Rules During Oral Defense
1. **Never Say "We just wanted to make...":**  
   * *Instead say:* "Based on our empirical baseline assessment and Sommerville's prototyping methodology..."
2. **Never Apologize for N = 3:**  
   * Own it! Say: *"Complete Enumeration of the total active user population in the College of Computer Studies."*
3. **Pause for 2 Seconds Before Answering:**  
   * Don't interrupt the panelist. Listen, nod, breathe, and begin with: *"Thank you for that question, Sir/Ma'am..."*
4. **Refer to Your Slides / Artifacts:**  
   * *"As shown in our Flowchart (Figure 3.2)..."* or *"As outlined in Table 4.1..."*
5. **Never Blame Your Groupmates:**  
   * Stand as one unified team. If your teammate is struggling, gently add: *"To supplement my colleague's point, the architecture specifically handles that by..."*
