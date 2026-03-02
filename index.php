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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #F7F9FB;
            color: #1F2933;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        a { color: #1E88E5; text-decoration: none; }
        a:hover { color: #1565C0; }

        /* Header / Nav */
        .header {
            background: #FFFFFF;
            border-bottom: 1px solid #E5E7EB;
            padding: 0 1.5rem;
        }
        .header-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 72px;
            gap: 1rem;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            text-decoration: none;
            color: inherit;
        }
        .header-logo:hover { color: inherit; }
        .header-logo img {
            height: 128px;
            width: auto;
            display: block;
        }
        .header-logo span {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1F2933;
            letter-spacing: -0.02em;
        }
        .header-nav {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .header-nav .nav-link {
            padding: 0.5rem 1rem;
            font-size: 15px;
            font-weight: 500;
            color: #2FAE66;
            border-radius: 6px;
            transition: color 0.2s, background-color 0.2s;
        }
        .header-nav .nav-link:hover {
            color: #268F52;
            background: #E8F5EE;
        }
        .header-nav .btn {
            padding: 10px 20px;
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
            margin-left: 0.5rem;
        }
        .header-nav .btn-primary {
            background: #1E88E5;
            color: #FFFFFF;
            border: none;
        }
        .header-nav .btn-primary:hover { background: #1565C0; color: #FFFFFF; }

        /* Hero */
        .hero {
            padding: 4rem 1.5rem 3rem;
            text-align: center;
            max-width: 640px;
            margin: 0 auto;
        }
        .hero .logo-large {
            height: 128px;
            width: auto;
            margin-bottom: 1.5rem;
        }
        .hero h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1F2933;
            margin-bottom: 0.5rem;
        }
        .hero .tagline {
            font-size: 18px;
            color: #2FAE66;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        .hero .intro {
            font-size: 16px;
            color: #5F6C7B;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .hero .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
        }
        .hero .btn {
            display: inline-block;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 500;
            font-family: inherit;
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.2s, border-color 0.2s;
        }
        .hero .btn-primary {
            background: #1E88E5;
            color: #FFFFFF;
            border: none;
        }
        .hero .btn-primary:hover { background: #1565C0; }
        .hero .btn-secondary {
            background: #FFFFFF;
            color: #1F2933;
            border: 1px solid #E5E7EB;
        }
        .hero .btn-secondary:hover {
            border-color: #1E88E5;
            color: #1E88E5;
        }

        /* Section */
        .section {
            padding: 3rem 1.5rem;
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
        }
        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: #1F2933;
            text-align: center;
            margin-bottom: 2rem;
        }
        .section-subtitle {
            font-size: 15px;
            color: #5F6C7B;
            text-align: center;
            max-width: 520px;
            margin: -1rem auto 2rem;
        }

        /* How it works - cards */
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }
        .card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }
        .card-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            background: #F7F9FB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            color: #1E88E5;
        }
        .card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1F2933;
            margin-bottom: 0.5rem;
        }
        .card p {
            font-size: 14px;
            color: #5F6C7B;
            line-height: 1.5;
        }

        /* Benefits strip */
        .benefits {
            background: #FFFFFF;
            border-top: 1px solid #E5E7EB;
            border-bottom: 1px solid #E5E7EB;
            padding: 2rem 1.5rem;
        }
        .benefits-inner {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
        }
        .benefit {
            font-size: 14px;
            color: #5F6C7B;
        }
        .benefit strong {
            display: block;
            font-size: 15px;
            font-weight: 600;
            color: #1F2933;
            margin-bottom: 0.25rem;
        }
        .benefit .green { color: #2FAE66; }

        /* Footer */
        .footer {
            margin-top: auto;
            padding: 2rem 1.5rem;
            text-align: center;
            border-top: 1px solid #E5E7EB;
        }
        .footer-logo {
            height: 52px;
            width: auto;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }
        .footer-tagline {
            font-size: 13px;
            color: #5F6C7B;
        }
        .footer-tagline span { color: #2FAE66; font-weight: 500; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a href="index.php" class="header-logo">
                <img src="public/assets/images/logo.png" alt="RePlug">
                <span>RePlug</span>
            </a>
            <nav class="header-nav">
                <a href="register.php" class="nav-link">Register</a>
                <a href="login.php" class="btn btn-primary">Log in</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <img src="public/assets/images/logo.png" alt="" class="logo-large" width="128" height="128">
        <h1>Recycle your electronics the right way</h1>
        <p class="tagline">Recycle • Repair • Reuse</p>
        <p class="intro">List your old electronics and small appliances for free pickup. We collect them, inspect and repair when possible, and give working items a second life through our marketplace. Simple, responsible, and good for the planet.</p>
        <div class="actions">
            <a href="register.php" class="btn btn-primary">Get started</a>
            <a href="login.php" class="btn btn-secondary">Log in</a>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">How it works</h2>
        <p class="section-subtitle">From your drawer to a new owner in four simple steps.</p>
        <div class="cards">
            <div class="card">
                <div class="card-icon">1</div>
                <h3>List your items</h3>
                <p>Add your electronics or small appliances with a short description and photos. It only takes a minute.</p>
            </div>
            <div class="card">
                <div class="card-icon">2</div>
                <h3>Free pickup</h3>
                <p>Request a pickup at your address. Our drivers collect your items at no cost to you.</p>
            </div>
            <div class="card">
                <div class="card-icon">3</div>
                <h3>Inspect & repair</h3>
                <p>Technicians check each item. Working or repairable devices are fixed and prepared for resale.</p>
            </div>
            <div class="card">
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
