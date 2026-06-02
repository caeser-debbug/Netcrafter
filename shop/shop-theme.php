<?php /* Shared shop theme — included after header.php in every shop page */ ?>
<style>
    /* ── Product Cards ─────────────────────────────── */
    .product-card {
        background: rgba(10,24,58,0.7);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(0,200,255,0.15);
        border-radius: 16px;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .product-card:hover {
        border-color: rgba(0,200,255,0.4);
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 20px rgba(0,200,255,0.1);
    }

    /* ── Shop sidebar/panel cards ──────────────────── */
    .shop-card {
        background: rgba(10,24,58,0.7);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(0,200,255,0.12);
        border-radius: 16px;
    }

    /* ── Price tag ─────────────────────────────────── */
    .price-tag { color: #00c8ff; font-weight: 700; }

    /* ── Category badge ───────────────────────────── */
    .cat-badge {
        background: rgba(0,200,255,0.15);
        color: #00c8ff;
        border: 1px solid rgba(0,200,255,0.3);
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 10px;
        border-radius: 50px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    /* ── Inputs ────────────────────────────────────── */
    .shop-input, .shop-select {
        background: rgba(10,24,58,0.8);
        border: 1px solid rgba(0,200,255,0.2);
        border-radius: 10px;
        color: #fff;
        padding: 10px 14px;
        width: 100%;
        transition: border-color 0.3s, box-shadow 0.3s;
        outline: none;
        appearance: none;
        -webkit-appearance: none;
    }
    .shop-input:focus, .shop-select:focus {
        border-color: rgba(0,200,255,0.5);
        box-shadow: 0 0 0 2px rgba(0,200,255,0.1);
    }
    .shop-input::placeholder { color: rgba(255,255,255,0.35); }

    /* ── Pagination ────────────────────────────────── */
    .pagination-btn {
        background: rgba(10,24,58,0.7);
        border: 1px solid rgba(0,200,255,0.2);
        color: #fff;
        padding: 8px 14px;
        border-radius: 8px;
        transition: all 0.3s;
        cursor: pointer;
        font-size: 0.875rem;
    }
    .pagination-btn:hover, .pagination-btn.active {
        background: rgba(0,200,255,0.15);
        border-color: rgba(0,200,255,0.5);
        color: #00c8ff;
    }

    /* ── Secondary outline button ──────────────────── */
    .btn-secondary {
        background: transparent;
        border: 1px solid rgba(0,200,255,0.3);
        color: #00c8ff;
        border-radius: 10px;
        padding: 10px 20px;
        transition: all 0.3s;
        cursor: pointer;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        text-decoration: none;
    }
    .btn-secondary:hover {
        background: rgba(0,200,255,0.1);
        border-color: rgba(0,200,255,0.6);
    }

    /* ── Page hero ─────────────────────────────────── */
    .shop-hero {
        background: linear-gradient(135deg, rgba(0,102,204,0.25), rgba(0,200,255,0.08));
        border-bottom: 1px solid rgba(0,200,255,0.12);
        padding: 116px 0 48px;
        position: relative;
        overflow: hidden;
    }

    /* ── Cart rows ─────────────────────────────────── */
    .cart-item {
        border-bottom: 1px solid rgba(0,200,255,0.07);
        transition: background 0.2s;
    }
    .cart-item:hover { background: rgba(0,200,255,0.03); }

    /* ── Product tabs ──────────────────────────────── */
    .tab-active {
        color: #00c8ff !important;
        border-color: #00c8ff !important;
    }

    /* ── Swiper ────────────────────────────────────── */
    .swiper-product  { height: 400px; }
    .swiper-thumbs   { height: 80px; }
    .swiper-slide    { display: flex; justify-content: center; align-items: center; }
    .swiper-slide img{ display: block; width: 100%; height: 100%; object-fit: contain; }
    .swiper-pagination-bullet-active { background-color: #00c8ff !important; }
    .swiper-button-next, .swiper-button-prev { color: #00c8ff !important; }
    .thumb-slide { opacity: 0.4; cursor: pointer; border: 2px solid transparent; transition: all 0.3s; }
    .thumb-slide-active { opacity: 1; border-color: #00c8ff; }

    /* ── Star ratings ──────────────────────────────── */
    .star-rating { display: inline-flex; align-items: center; }
    .star-rating .star { color: #374151; }
    .star-rating .star.filled { color: #fbbf24; }

    /* ── Payment method cards ──────────────────────── */
    .payment-method-card {
        background: rgba(10,24,58,0.7);
        border: 1px solid rgba(0,200,255,0.15);
        border-radius: 12px;
        transition: all 0.3s;
        cursor: pointer;
    }
    .payment-method-card:hover, .payment-method-card.selected {
        border-color: #00c8ff;
        background: rgba(0,200,255,0.08);
        box-shadow: 0 0 16px rgba(0,200,255,0.15);
    }

    /* ── Custom file input ─────────────────────────── */
    .custom-file-input::-webkit-file-upload-button { visibility: hidden; width: 0; }
    .custom-file-input::before {
        content: 'Choisir un fichier';
        display: inline-block;
        background: rgba(0,200,255,0.1);
        border: 1px solid rgba(0,200,255,0.3);
        border-radius: 8px;
        padding: 6px 14px;
        font-size: 0.85rem;
        color: #00c8ff;
        cursor: pointer;
        font-weight: 500;
        white-space: nowrap;
    }
    .custom-file-input:hover::before { background: rgba(0,200,255,0.18); }

    /* ── No-spinner number input ───────────────────── */
    input[type=number].no-arrows::-webkit-inner-spin-button,
    input[type=number].no-arrows::-webkit-outer-spin-button { appearance: none; -webkit-appearance: none; }
    input[type=number].no-arrows { -moz-appearance: textfield; }

    /* ── Radio custom ──────────────────────────────── */
    .radio-nc { accent-color: #00c8ff; }
</style>

<script>
/* Inject cart/favorites count badges into the main navbar */
document.addEventListener('DOMContentLoaded', function() {
    const cartTotal  = <?= array_sum($_SESSION['cart'] ?? []) ?>;
    const favTotal   = <?= count($_SESSION['favorites'] ?? []) ?>;

    const navDesktop = document.querySelector('#navbar .hidden.md\\:flex');
    if (!navDesktop) return;

    const shopIcons = document.createElement('div');
    shopIcons.className = 'flex items-center gap-4 mr-2';
    shopIcons.innerHTML =
        '<a href="favorites.php" class="relative text-gray-300 hover:text-nc-cyan transition-colors">' +
            '<i class="fas fa-heart text-lg"></i>' +
            (favTotal > 0 ? '<span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center leading-none">' + favTotal + '</span>' : '') +
        '</a>' +
        '<a href="cart.php" class="relative text-gray-300 hover:text-nc-cyan transition-colors">' +
            '<i class="fas fa-shopping-cart text-lg"></i>' +
            (cartTotal > 0 ? '<span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center leading-none">' + cartTotal + '</span>' : '') +
        '</a>';

    const devisBtn = navDesktop.querySelector('a[href*="devis"]');
    if (devisBtn) { navDesktop.insertBefore(shopIcons, devisBtn); }
    else          { navDesktop.appendChild(shopIcons); }
});
</script>
