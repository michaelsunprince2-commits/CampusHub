<?php

/**
 * Public Security Policy Page
 */

$pageTitle = 'Security Policy';
require_once '../templates/header.php';
?>

<style>
    .policy-page {
        max-width: 920px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid #e5edf2;
        border-radius: 8px;
        padding: 2rem;
        box-shadow: 0 6px 18px rgba(44, 62, 80, 0.07);
    }

    .policy-page h1 {
        margin-bottom: 0.75rem;
        color: #2c3e50;
    }

    .policy-intro {
        color: #657786;
        font-size: 1.05rem;
        line-height: 1.65;
        margin-bottom: 2rem;
    }

    .policy-section {
        padding-top: 1.5rem;
        margin-top: 1.5rem;
        border-top: 1px solid #edf2f5;
    }

    .policy-section h2 {
        margin-bottom: 0.75rem;
        color: #2c3e50;
        font-size: 1.35rem;
    }

    .policy-section p,
    .policy-section li {
        color: #34495e;
        line-height: 1.65;
    }

    .policy-section ul {
        margin-left: 1.25rem;
        display: grid;
        gap: 0.45rem;
    }

    .security-contact {
        display: grid;
        gap: 0.5rem;
        padding: 1rem;
        border-radius: 8px;
        background: #eef5f6;
        border: 1px solid #d7e8eb;
    }
</style>

<div class="policy-page">
    <h1>Security Policy</h1>
    <p class="policy-intro">
        CampusNest is built to help students and landlords connect safely around housing. This page explains how security concerns should be reported and how users can help keep accounts, listings, bookings, messages, documents, images, and videos protected.
    </p>

    <section class="policy-section">
        <h2>Reporting Security Issues</h2>
        <div class="security-contact">
            <p>If you discover a security issue, please report it as soon as possible.</p>
            <p><strong>Email:</strong> <a href="mailto:support@campusnest.local">support@campusnest.local</a></p>
            <p>Use the subject line: <strong>Security Report - CampusNest</strong></p>
        </div>
    </section>

    <section class="policy-section">
        <h2>What To Report</h2>
        <ul>
            <li>Unauthorized access to another user's account, booking, message, or profile.</li>
            <li>Ability to edit, delete, approve, or reject records without permission.</li>
            <li>Exposure of private documents, payment details, uploads, or personal information.</li>
            <li>Login, password reset, session, or role-related weaknesses.</li>
            <li>File upload issues that allow unsafe files or unexpected access.</li>
        </ul>
    </section>

    <section class="policy-section">
        <h2>Responsible Testing</h2>
        <p>Please avoid actions that could harm users, landlords, students, or the platform. Do not attempt to download private data, disrupt service, spam users, access accounts you do not own, or publicly disclose a vulnerability before CampusNest has had a reasonable chance to review it.</p>
    </section>

    <section class="policy-section">
        <h2>How We Handle Reports</h2>
        <ul>
            <li>We review reported issues and may ask for safe reproduction steps.</li>
            <li>We prioritize issues that affect account access, private data, payments, bookings, or verification documents.</li>
            <li>We work to resolve confirmed issues and reduce the chance of similar problems happening again.</li>
        </ul>
    </section>

    <section class="policy-section">
        <h2>Account Safety Tips</h2>
        <ul>
            <li>Use a strong password and keep it private.</li>
            <li>Do not share login details with landlords, students, or third parties.</li>
            <li>Review property details carefully before booking or making payments.</li>
            <li>Report suspicious messages, listings, payment requests, or account activity.</li>
        </ul>
    </section>
</div>

<?php require_once '../templates/footer.php'; ?>
