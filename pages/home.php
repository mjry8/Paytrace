<?php
$title = 'PayGuard | Secure Transactions';
require __DIR__ . '/_header.php';
?>

<section class="hero">
    <div class="hero-text">
        <h1>PayGuard.<br><span>Empower your finances.</span></h1>
        <p>The ultimate front-end for secure digital payments. Every transaction is logged, tracked, and guaranteed to leave a trace. If the gateway fails, our PayTrace engine recovers it.</p>
        
        <a href="<?= url('checkout') ?>" class="btn-primary">Start a Payment</a>
        <a href="<?= url('login') ?>" class="btn-secondary">Staff Login</a>
    </div>

    <div class="hero-graphic">
        <div class="css-wallet">
            <div class="css-card">
                <div class="chip"></div>
                <div class="amount">KES 2,500</div>
            </div>
        </div>
    </div>
</section>

<section class="features">
    <h2>Why PayGuard?</h2>
    <div class="feature-grid">
        <div class="feature-card">
            <h3>1. No Disappearing Records</h3>
            <p>Powered by <b>PayTrace</b>. We save the transaction <i>before</i> calling the gateway. Even if the network drops, the record survives.</p>
        </div>
        <div class="feature-card">
            <h3>2. Instant Reconciliation</h3>
            <p>Staff can compare our records with the gateway's ledger in one click. Duplicate charges are flagged instantly.</p>
        </div>
        <div class="feature-card">
            <h3>3. Customer Trust</h3>
            <p>Customers get instant receipts and a direct way to report issues. Transparency builds confidence in your platform.</p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/_footer.php'; ?>