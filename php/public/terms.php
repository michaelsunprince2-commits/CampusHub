<?php

/**
 * Public Terms of Service Page
 */

$pageTitle = 'Terms of Service';
require_once '../templates/header.php';
?>

<style>
    .terms-page {
        max-width: 940px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid #e5edf2;
        border-radius: 8px;
        padding: 2rem;
        box-shadow: 0 6px 18px rgba(44, 62, 80, 0.07);
    }

    .terms-page h1 {
        margin-bottom: 0.75rem;
        color: #2c3e50;
    }

    .terms-intro {
        color: #657786;
        font-size: 1.05rem;
        line-height: 1.65;
        margin-bottom: 2rem;
    }

    .terms-section {
        padding-top: 1.5rem;
        margin-top: 1.5rem;
        border-top: 1px solid #edf2f5;
    }

    .terms-section h2 {
        margin-bottom: 0.75rem;
        color: #2c3e50;
        font-size: 1.35rem;
    }

    .terms-section p,
    .terms-section li {
        color: #34495e;
        line-height: 1.65;
    }

    .terms-section ul {
        margin-left: 1.25rem;
        display: grid;
        gap: 0.45rem;
    }

    .terms-note {
        padding: 1rem;
        border-radius: 8px;
        background: #eef5f6;
        border: 1px solid #d7e8eb;
        color: #34495e;
        line-height: 1.6;
    }
</style>

<div class="terms-page">
    <h1>Terms of Service</h1>
    <p class="terms-intro">
        These Terms of Service explain the basic rules for using CampusNest. By creating an account, listing a property, booking accommodation, sending messages, uploading content, or using the platform, you agree to use CampusNest responsibly and lawfully.
    </p>

    <div class="terms-note">
        This page is a practical starting policy for CampusNest. It should be reviewed by a qualified legal professional before commercial launch or public deployment.
    </div>

    <section class="terms-section">
        <h2>1. Accounts</h2>
        <ul>
            <li>Users must provide accurate registration and profile information.</li>
            <li>Students, landlords, admins, and committee users may have different access permissions.</li>
            <li>You are responsible for keeping your password and account access secure.</li>
            <li>You must not create accounts using false identity information or impersonate another person.</li>
        </ul>
    </section>

    <section class="terms-section">
        <h2>2. Landlord Listings</h2>
        <ul>
            <li>Landlords are responsible for listing accurate property details, prices, availability, rules, images, and videos.</li>
            <li>Uploaded property media must represent the actual property being listed.</li>
            <li>CampusNest may review, verify, reject, hide, or remove listings that appear unsafe, misleading, incomplete, or fraudulent.</li>
            <li>Landlords must have the right to advertise and rent any property they list.</li>
        </ul>
    </section>

    <section class="terms-section">
        <h2>3. Student Bookings</h2>
        <ul>
            <li>Students should review property details carefully before booking.</li>
            <li>Bookings may be subject to landlord approval, property availability, platform checks, and payment confirmation.</li>
            <li>Students must not submit false booking information or misuse landlord contact details.</li>
        </ul>
    </section>

    <section class="terms-section">
        <h2>4. Payments</h2>
        <p>Where payments are available, CampusNest may use third-party payment processors to handle transactions. Payment availability, confirmation, refunds, and receipts may depend on the payment provider and the booking status shown on the platform.</p>
    </section>

    <section class="terms-section">
        <h2>5. Messages And Calls</h2>
        <ul>
            <li>Users must communicate respectfully and only for housing-related purposes.</li>
            <li>Harassment, threats, spam, fraud, and abusive communication are not allowed.</li>
            <li>CampusNest may restrict accounts that misuse messaging or calling features.</li>
        </ul>
    </section>

    <section class="terms-section">
        <h2>6. Uploaded Content</h2>
        <p>You keep ownership of content you upload, such as profile pictures, property images, videos, documents, messages, and reviews. By uploading content, you give CampusNest permission to store, process, and display that content as needed to operate the platform.</p>
        <p>You must not upload content that is illegal, misleading, harmful, offensive, stolen, or violates another person's rights.</p>
    </section>

    <section class="terms-section">
        <h2>7. Reviews And Ratings</h2>
        <ul>
            <li>Reviews should be honest, relevant, and based on real experience.</li>
            <li>False, abusive, paid, or manipulated reviews are not allowed.</li>
            <li>CampusNest may remove reviews that violate platform rules or appear fraudulent.</li>
        </ul>
    </section>

    <section class="terms-section">
        <h2>8. Prohibited Use</h2>
        <ul>
            <li>Do not attempt to access another user's account or private information.</li>
            <li>Do not upload unsafe files, malware, or misleading documents.</li>
            <li>Do not bypass booking, payment, verification, or security controls.</li>
            <li>Do not use CampusNest for fraud, harassment, discrimination, or unlawful activity.</li>
        </ul>
    </section>

    <section class="terms-section">
        <h2>9. Account Restrictions</h2>
        <p>CampusNest may suspend, restrict, or remove accounts, listings, messages, reviews, or uploads if they violate these terms, appear fraudulent, create safety concerns, or harm the platform community.</p>
    </section>

    <section class="terms-section">
        <h2>10. Changes To These Terms</h2>
        <p>CampusNest may update these Terms of Service as the platform grows. Continued use of the platform after changes means you accept the updated terms.</p>
    </section>

    <section class="terms-section">
        <h2>Contact</h2>
        <p>For questions about these terms, contact <a href="mailto:support@campusnest.local">support@campusnest.local</a>.</p>
    </section>
</div>

<?php require_once '../templates/footer.php'; ?>
