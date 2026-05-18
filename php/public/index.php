<?php

/**
 * Landing Page / Home Page
 */

$pageTitle = 'Home';
require_once '../templates/header.php';
?>

<div class="hero">
    <style>
        .hero {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(90deg, rgba(36, 51, 66, 0.9) 0%, rgba(31, 111, 120, 0.78) 48%, rgba(36, 51, 66, 0.62) 100%),
                url('<?php echo getBaseUrl(); ?>/php/public/assets/hero.avif');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 6rem 2.5rem;
            text-align: left;
            margin-bottom: 3rem;
            border-radius: 8px;
            min-height: 520px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 16px 34px rgba(36, 51, 66, 0.18);
            isolation: isolate;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0));
            pointer-events: none;
            z-index: -1;
        }

        .hero h1 {
            max-width: 720px;
            font-size: 3.6rem;
            line-height: 1.08;
            margin-bottom: 1rem;
            color: #ffffff;
            text-shadow: 0 2px 16px rgba(0, 0, 0, 0.25);
            font-weight: 800;
        }

        .hero p {
            max-width: 620px;
            font-size: 1.35rem;
            margin-bottom: 2rem;
            color: #edf6f7;
            text-shadow: 0 1px 10px rgba(0, 0, 0, 0.22);
        }

        .search-form {
            display: flex;
            gap: 1rem;
            max-width: 760px;
            margin: 2rem 0 0;
            flex-wrap: wrap;
            justify-content: flex-start;
            padding: 0.85rem;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 12px 30px rgba(17, 30, 42, 0.2);
            backdrop-filter: blur(8px);
        }

        .search-form input,
        .search-form select {
            min-height: 48px;
            padding: 0.8rem 0.9rem;
            border: 1px solid #dbe6ec;
            border-radius: 7px;
            font-size: 1rem;
            color: #243342;
            background: #ffffff;
        }

        .search-form input {
            flex: 1 1 280px;
        }

        .search-form select {
            flex: 0 1 180px;
        }

        .search-form input:focus,
        .search-form select:focus {
            outline: none;
            border-color: #1f6f78;
            box-shadow: 0 0 0 3px rgba(31, 111, 120, 0.14);
        }

        .search-form .btn {
            min-height: 48px;
            border-radius: 7px;
            background: #1f6f78;
            font-weight: 800;
            padding: 0.8rem 1.35rem;
        }

        .search-form .btn:hover {
            background: #195f67;
        }

        @media (max-width: 768px) {
            .hero {
                min-height: 480px;
                padding: 4.5rem 1.25rem;
            }

            .hero h1 {
                font-size: 2.45rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .search-form {
                padding: 0.75rem;
            }

            .search-form input,
            .search-form select,
            .search-form .btn {
                flex: 1 1 100%;
                width: 100%;
            }
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .card-image {
            width: 100%;
            height: 200px;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            position: relative;
        }

        .property-badge {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            z-index: 2;
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            background: #e9f7ef;
            color: #1e8449;
            font-size: 0.82rem;
            font-weight: 800;
            box-shadow: 0 6px 14px rgba(36, 51, 66, 0.14);
        }

        .property-badge.booked {
            background: #fdecea;
            color: #922b21;
        }

        .card-content {
            padding: 1.5rem;
        }

        .card-title {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        .card-price {
            font-size: 1.5rem;
            color: #27ae60;
            margin-bottom: 1rem;
        }

        .rating {
            color: #f39c12;
            margin-bottom: 1rem;
        }

        .testimonials-section {
            margin: 4rem 0;
            padding: 3rem 0;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 8px;
            padding: 3rem 1rem;
        }

        .testimonials-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #2c3e50;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .testimonial-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .testimonial-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .ambassador-avatar {
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        .ambassador-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ambassador-avatar.male {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .ambassador-avatar.female {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .ambassador-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .ambassador-title {
            font-size: 0.95rem;
            color: #7f8c8d;
            margin-bottom: 1.5rem;
        }

        .testimonial-quote {
            font-size: 1.1rem;
            font-style: italic;
            color: #34495e;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .testimonial-stars {
            color: #f39c12;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }

        .feature {
            text-align: center;
            padding: 1.5rem;
        }

        .feature-icon {
            margin-bottom: 1rem;
        }

        .feature-icon svg {
            width: 2.4rem;
            height: 2.4rem;
            color: #1f6f78;
            stroke-width: 2;
        }

        .campus-section {
            margin: 4rem 0 3rem;
            padding: 2.5rem 1.5rem;
            background: #ffffff;
            border: 1px solid #e5edf2;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(44, 62, 80, 0.07);
        }

        .campus-header {
            max-width: 760px;
            margin-bottom: 1.75rem;
        }

        .campus-header h2 {
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .campus-header p {
            color: #657786;
            font-size: 1.05rem;
            margin-bottom: 0;
        }

        .campus-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .campus-card {
            display: grid;
            gap: 0.9rem;
            padding: 1rem;
            border: 1px solid #dfeaf0;
            border-radius: 8px;
            background: #fbfcfd;
        }

        .campus-card-header {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .campus-mark {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: #eef5f6;
            color: #1f6f78;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .campus-name {
            color: #2c3e50;
            font-weight: 800;
        }

        .campus-location {
            color: #657786;
            font-size: 0.9rem;
            margin-top: 0.2rem;
        }

        .campus-list {
            display: grid;
            gap: 0.55rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .campus-list li {
            padding: 0.55rem 0.7rem;
            border-radius: 7px;
            background: #ffffff;
            color: #34495e;
            border: 1px solid #edf2f5;
            font-size: 0.95rem;
            line-height: 1.35;
        }
    </style>

    <h1>Welcome to CampusNest</h1>
    <p>Find your perfect student housing with ease</p>

    <form class="search-form" method="get" action="<?php echo pageUrl('properties.php'); ?>">
        <input type="text" name="city" placeholder="Search by city..." value="<?php echo htmlspecialchars($_GET['city'] ?? ''); ?>">
        <select name="property_type">
            <option value="">All Types</option>
            <option value="apartment" <?php echo ($_GET['property_type'] ?? '') === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
            <option value="house" <?php echo ($_GET['property_type'] ?? '') === 'house' ? 'selected' : ''; ?>>House</option>
            <option value="dorm" <?php echo ($_GET['property_type'] ?? '') === 'dorm' ? 'selected' : ''; ?>>Dorm</option>
        </select>
        <button type="submit" class="btn">Search</button>
    </form>
</div>

<section class="campus-section">
    <div class="campus-header">
        <h2>Find Housing Near Your Campus</h2>
        <p>CampusNest helps students discover verified housing around key campus areas. These are areas we support, not official university partnerships.</p>
    </div>
    <div class="campus-grid">
        <div class="campus-card">
            <div class="campus-card-header">
                <div class="campus-mark">NS</div>
                <div>
                    <div class="campus-name">Nasarawa & Lafia</div>
                    <div class="campus-location">Keffi, Lafia, and nearby student areas</div>
                </div>
            </div>
            <ul class="campus-list">
                <li>Nasarawa State University, Keffi</li>
                <li>Lincoln University Malaysia NSUK Campus</li>
                <li>Federal University of Lafia</li>
            </ul>
        </div>
        <div class="campus-card">
            <div class="campus-card-header">
                <div class="campus-mark">FCT</div>
                <div>
                    <div class="campus-name">Abuja / FCT Universities</div>
                    <div class="campus-location">Government and private university areas</div>
                </div>
            </div>
            <ul class="campus-list">
                <li>University of Abuja</li>
                <li>National Open University of Nigeria</li>
                <li>Nile University of Nigeria</li>
                <li>Baze University</li>
                <li>Veritas University, Abuja</li>
                <li>African University of Science and Technology</li>
                <li>Philomath University, Kuje</li>
            </ul>
        </div>
        <div class="campus-card">
            <div class="campus-card-header">
                <div class="campus-mark">BN</div>
                <div>
                    <div class="campus-name">Benue State Universities</div>
                    <div class="campus-location">Makurdi, Otukpo, Mkar, and Ihugh areas</div>
                </div>
            </div>
            <ul class="campus-list">
                <li>Joseph Sarwuan Tarka University, Makurdi</li>
                <li>Reverend Father Moses Orshio Adasu University, Makurdi</li>
                <li>Federal University of Health Sciences, Otukpo</li>
                <li>University of Mkar</li>
                <li>Benue State University of Agriculture, Science and Technology, Ihugh</li>
            </ul>
        </div>
    </div>
</section>

<h2>Why Choose CampusNest?</h2>
<div class="features">
    <div class="feature">
        <div class="feature-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m16.5 16.5 4 4"></path>
            </svg>
        </div>
        <h3>Easy Search</h3>
        <p>Browse thousands of student properties with detailed information and photos</p>
    </div>
    <div class="feature">
        <div class="feature-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"></path>
                <path d="M21 12a9 9 0 1 1-5.3-8.2"></path>
            </svg>
        </div>
        <h3>Verified Listings</h3>
        <p>All properties are verified by our team to ensure quality and safety</p>
    </div>
    <div class="feature">
        <div class="feature-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path>
                <path d="M8 9h8"></path>
                <path d="M8 13h5"></path>
            </svg>
        </div>
        <h3>Direct Messaging</h3>
        <p>Communicate directly with landlords and other students</p>
    </div>
    <div class="feature">
        <div class="feature-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                <path d="M3 10h18"></path>
                <path d="M7 15h3"></path>
            </svg>
        </div>
        <h3>Secure Payment</h3>
        <p>Safe and secure payment processing for bookings</p>
    </div>
</div>

<h2 style="margin-top: 3rem;">Featured Properties</h2>
<div class="grid">
    <?php
    require_once '../models/Property.php';
    $property = new Property($conn);
    $properties = $property->listProperties([], 6, 0);

    if (empty($properties)): ?>
        <p>No properties available yet. Check back soon!</p>
        <?php else:
        foreach ($properties as $prop): ?>
            <?php $isBooked = !empty($prop['active_booking_count']); ?>
            <div class="card">
                <div class="card-image">
                    <span class="property-badge <?php echo $isBooked ? 'booked' : ''; ?>"><?php echo $isBooked ? 'Booked' : 'Available'; ?></span>
                    <?php
                    $images = $prop['image_urls'];
                    if (!empty($images) && is_array($images) && !empty($images[0])):
                    ?>
                        <img src="<?php echo htmlspecialchars($images[0]); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo htmlspecialchars($prop['name']); ?>">
                    <?php else: ?>
                        🏘️
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <div class="card-title"><?php echo htmlspecialchars($prop['name']); ?></div>
                    <div class="card-price"><?php echo formatCurrency($prop['price_per_month']); ?>/month</div>
                    <div class="rating">
                        <?php
                        $rating = $prop['avg_rating'];
                        if ($rating > 0):
                            echo '⭐ ' . number_format($rating, 1) . ' (' . $prop['review_count'] . ' reviews)';
                        else:
                            echo 'No ratings yet';
                        endif;
                        ?>
                    </div>
                    <p><?php echo htmlspecialchars(substr($prop['description'], 0, 100)) . '...'; ?></p>
                    <p><strong><?php echo $prop['bedrooms']; ?> bed</strong> | <strong><?php echo $prop['bathrooms']; ?> bath</strong></p>
                    <a href="<?php echo pageUrl('property-details.php?id=' . $prop['id']); ?>" class="btn">View Details</a>
                </div>
            </div>
    <?php endforeach;
    endif; ?>
</div>

<div class="testimonials-section">
    <h2 class="testimonials-title">Student Ambassadors Share Their Experience</h2>
    <div class="testimonials-grid">
        <div class="testimonial-card">
            <div class="ambassador-avatar male"><img src="<?php echo getBaseUrl(); ?>/php/uploads/profiles/segun.png" alt="Marcus Johnson"></div>
            <div class="ambassador-name">Marcus Johnson</div>
            <div class="ambassador-title">Senior @ State University</div>
            <div class="testimonial-stars">⭐⭐⭐⭐⭐</div>
            <div class="testimonial-quote">
                "CampusNest made finding my perfect apartment so easy! Within a week, I found a spacious, affordable place close to campus. The verified listings gave me peace of mind."
            </div>
            <p style="color: #7f8c8d; font-size: 0.9rem;">✓ Lived here for 2 years</p>
        </div>

        <div class="testimonial-card">
            <div class="ambassador-avatar female"><img src="<?php echo getBaseUrl(); ?>/php/public/assets/female2.jpg" alt="Sophia Williams"></div>
            <div class="ambassador-name">Sophia Williams</div>
            <div class="ambassador-title">Junior @ Tech Institute</div>
            <div class="testimonial-stars">⭐⭐⭐⭐⭐</div>
            <div class="testimonial-quote">
                "As an international student, finding safe and comfortable housing was my biggest worry. CampusNest connected me with an amazing landlord and a welcoming community!"
            </div>
            <p style="color: #7f8c8d; font-size: 0.9rem;">✓ Living here for 1.5 years</p>
        </div>

        <div class="testimonial-card">
            <div class="ambassador-avatar female"><img src="<?php echo getBaseUrl(); ?>/php/public/assets/female3.jpg" alt="Emma Rodriguez"></div>
            <div class="ambassador-name">Emma Rodriguez</div>
            <div class="ambassador-title">Sophomore @ Arts University</div>
            <div class="testimonial-stars">⭐⭐⭐⭐⭐</div>
            <div class="testimonial-quote">
                "I love how transparent everything is on CampusNest. Real photos, honest reviews from other students, and secure payments. It's the student housing platform I wish I had found earlier!"
            </div>
            <p style="color: #7f8c8d; font-size: 0.9rem;">✓ Verified member for 1 year</p>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
