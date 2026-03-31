<?php
/**
 * RePlug — Landing Page
 * Recycle • Repair • Reuse
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RePlug — Recycle • Repair • Reuse</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/assets/css/app.css">
    <style>
        .hero {
            padding: 4rem 1.5rem 3rem;
            text-align: center;
            max-width: 640px;
            margin: 0 auto;
        }
        .hero-logo {
            height: 120px;
            width: auto;
            margin-bottom: 1.25rem;
        }
        .hero-title {
            font-size: 2rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 0.35rem;
        }
        .hero-tagline {
            font-size: 1.05rem;
            color: #22C55E;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .hero-intro {
            font-size: 0.98rem;
            color: #6B7280;
            line-height: 1.7;
            margin-bottom: 1.75rem;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }
        .section {
            padding: 3rem 1.5rem;
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
        }
        .section-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: #0F172A;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .section-subtitle {
            font-size: 0.92rem;
            color: #6B7280;
            text-align: center;
            max-width: 520px;
            margin: 0 auto 1.75rem;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.4rem;
        }
        .card-landing {
            background: #ffffff;
            border-radius: 0.9rem;
            border: 1px solid #E5E7EB;
            padding: 1.5rem 1.4rem;
            text-align: left;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.8rem;
            background: #EFF6FF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1D4ED8;
            margin-bottom: 0.7rem;
        }
        .card-landing h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
        }
        .card-landing p {
            font-size: 0.9rem;
            color: #6B7280;
        }
        .benefits {
            background: #ffffff;
            border-top: 1px solid #E5E7EB;
            border-bottom: 1px solid #E5E7EB;
            padding: 2rem 1.5rem;
        }
        .benefits-inner {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.7rem;
            text-align: left;
        }
        .benefit {
            font-size: 0.9rem;
            color: #6B7280;
        }
        .benefit strong {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        .benefit .green {
            color: #22C55E;
        }
        .footer {
            margin-top: auto;
            padding: 2rem 1.5rem;
            text-align: center;
            border-top: 1px solid #E5E7EB;
        }
        .footer-logo {
            height: 48px;
            width: auto;
            margin-bottom: 0.4rem;
            opacity: 0.95;
        }
        .footer-tagline {
            font-size: 0.8rem;
            color: #6B7280;
        }
        .footer-tagline span {
            color: #22C55E;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <header class="rp-header">
        <div class="rp-header-inner">
            <a href="index.php" class="rp-logo">
                <img src="public/assets/images/logo.png" alt="RePlug">
                <span>RePlug</span>
            </a>
            <nav class="rp-nav">
                <a href="marketplace.php" class="rp-nav-link">Marketplace</a>
                <a href="register.php" class="rp-nav-link">Register</a>
                <a href="login.php" class="btn btn-primary">Log in</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <img src="public/assets/images/logo.png" alt="" class="hero-logo" width="128" height="128">
        <h1 class="hero-title">Recycle your electronics the right way</h1>
        <p class="hero-tagline">Recycle • Repair • Reuse</p>
        <p class="hero-intro">
            List your old electronics and small appliances for free pickup. We collect them, inspect and repair when possible,
            and give working items a second life through our marketplace.
        </p>
        <div class="hero-actions">
            <a href="marketplace.php" class="btn btn-secondary">Browse marketplace</a>
            <a href="register.php" class="btn btn-primary">Get started</a>
            <a href="login.php" class="btn btn-secondary">Log in</a>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">How it works</h2>
        <p class="section-subtitle">From your drawer to a new owner in four simple steps.</p>
        <div class="cards">
            <div class="card-landing">
                <div class="card-icon">1</div>
                <h3>List your items</h3>
                <p>Add your electronics or small appliances with a short description and photos. It only takes a minute.</p>
            </div>
            <div class="card-landing">
                <div class="card-icon">2</div>
                <h3>Free pickup</h3>
                <p>Request a pickup at your address. Our drivers collect your items at no cost to you.</p>
            </div>
            <div class="card-landing">
                <div class="card-icon">3</div>
                <h3>Inspect & repair</h3>
                <p>Technicians check each item. Working or repairable devices are fixed and prepared for resale.</p>
            </div>
            <div class="card-landing">
                <div class="card-icon">4</div>
                <h3>Reuse or recycle</h3>
                <p>Good items are sold in our marketplace. The rest are recycled responsibly. You may earn a share when your item sells.</p>
            </div>
        </div>
    </section>

    <section class="benefits">
        <div class="benefits-inner">
            <div class="benefit">
                <strong>Free pickup</strong>
                No cost to drop off your old devices. We come to you.
            </div>
            <div class="benefit">
                <strong class="green">Eco-friendly</strong>
                Fewer devices in landfills. Repair and reuse before recycle.
            </div>
            <div class="benefit">
                <strong>Transparent process</strong>
                Track your items from pickup to inspection to sale.
            </div>
        </div>
    </section>

    <footer class="footer">
        <img src="public/assets/images/logo.png" alt="" class="footer-logo" width="52" height="52">
        <p class="footer-tagline"><span>Recycle • Repair • Reuse</span> — A modern recycling depot for electronics.</p>
    </footer>
</body>
</html>
