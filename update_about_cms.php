<?php
require 'includes/db.php';
$new_content = <<<HTML
<div class="container py-5">
    <!-- Introduction Section -->
    <div class="row align-items-center mb-5 pb-5 border-bottom">
        <div class="col-md-6 mb-4 mb-md-0">
            <h2 class="fw-bold text-warning mb-3">Who is Lord Vishwakarma?</h2>
            <p class="text-muted" style="line-height: 1.8; text-align: justify;">
                Lord Vishwakarma is universally acknowledged as the principal architect of the universe. In Hindu mythology, He is the divine draftsman, the engineer of the gods, and the creator of the world. The Rig Veda describes Him as the ultimate reality, from whose navel all visible things emerge. 
            </p>
            <p class="text-muted" style="line-height: 1.8; text-align: justify;">
                He is credited with building the celestial cities like <strong>Swarga</strong> (Heaven), <strong>Lanka</strong> (the city of gold for Kubera), <strong>Dwarka</strong> (the capital of Lord Krishna), and <strong>Indraprastha</strong> (for the Pandavas). He is also the creator of powerful divine weapons, including Lord Shiva's Trishul, Lord Vishnu's Sudarshana Chakra, and Indra's Vajra.
            </p>
        </div>
        <div class="col-md-6 text-center">
            <img src="https://placehold.co/500x500/f39c12/white?text=Lord+Vishwakarma" class="img-fluid rounded-circle shadow-lg border border-5 border-warning" alt="Lord Vishwakarma">
        </div>
    </div>

    <!-- The Five Gotras Section -->
    <div class="row mb-5 pb-5 border-bottom">
        <div class="col-12 text-center mb-5">
            <h2 class="fw-bold text-primary">The Five Divine Lineages (Gotras)</h2>
            <p class="text-muted">According to the scriptures, Lord Vishwakarma had five sons, who became the progenitors of the five primary artisan groups of the Vishwakarma Samaj.</p>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card card-custom h-100 p-4 border-top border-4 border-warning shadow-sm hover-lift text-center">
                <i class="fa-solid fa-fire fa-3x text-warning mb-3"></i>
                <h4 class="fw-bold">Manu (Lohar)</h4>
                <p class="text-muted small">Masters of iron and metallurgy. They forged weapons, tools, and agricultural implements, laying the foundation for human civilization.</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card card-custom h-100 p-4 border-top border-4 border-success shadow-sm hover-lift text-center">
                <i class="fa-solid fa-tree fa-3x text-success mb-3"></i>
                <h4 class="fw-bold">Maya (Sutar/Badhai)</h4>
                <p class="text-muted small">Masters of woodcraft and architecture. They constructed chariots, ships, temples, and homes, providing shelter and transport.</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card card-custom h-100 p-4 border-top border-4 border-info shadow-sm hover-lift text-center">
                <i class="fa-solid fa-gavel fa-3x text-info mb-3"></i>
                <h4 class="fw-bold">Tvashta (Kasar/Thathera)</h4>
                <p class="text-muted small">Masters of copper, brass, and alloys. They created sacred vessels, utensils, and artifacts essential for daily life and religious rituals.</p>
            </div>
        </div>
        <div class="col-md-6 mb-4 d-flex justify-content-center">
            <div class="card card-custom w-75 p-4 border-top border-4 border-secondary shadow-sm hover-lift text-center">
                <i class="fa-solid fa-hammer fa-3x text-secondary mb-3"></i>
                <h4 class="fw-bold">Shilpi (Muritkar/Kumbhar)</h4>
                <p class="text-muted small">Masters of stone-carving, sculpting, and pottery. They brought the divine into physical form by carving intricate temple idols.</p>
            </div>
        </div>
        <div class="col-md-6 mb-4 d-flex justify-content-center">
            <div class="card card-custom w-75 p-4 border-top border-4 border-primary shadow-sm hover-lift text-center">
                <i class="fa-solid fa-gem fa-3x text-primary mb-3"></i>
                <h4 class="fw-bold">Vishvajna (Sonar/Swarnkar)</h4>
                <p class="text-muted small">Masters of gold, silver, and precious stones. They designed exquisite jewelry and ornaments, symbolizing prosperity and beauty.</p>
            </div>
        </div>
    </div>

    <!-- Modern Era Section -->
    <div class="row align-items-center mb-5 pb-5">
        <div class="col-md-6 order-2 order-md-1">
            <img src="https://placehold.co/600x400/34495e/white?text=Modern+Engineering" class="img-fluid rounded shadow-lg" alt="Modern Engineering">
        </div>
        <div class="col-md-6 order-1 order-md-2 mb-4 mb-md-0">
            <h2 class="fw-bold text-success mb-3">The Modern Era: From Artisans to Technocrats</h2>
            <p class="text-muted" style="line-height: 1.8; text-align: justify;">
                While the tools have evolved from hammers and chisels to computers and robotics, the spirit of creation remains in our DNA. Today, the Vishwakarma Samaj is not just limited to traditional craftsmanship.
            </p>
            <p class="text-muted" style="line-height: 1.8; text-align: justify;">
                Our community members are leading civil engineers, software developers, architects, industrial manufacturers, and scientists. From building smart cities to writing complex code that runs the digital world, the descendants of Lord Vishwakarma continue to build the future of India and the world.
            </p>
            <p class="text-muted" style="line-height: 1.8; text-align: justify;">
                <strong>About This Portal:</strong> Recognizing the need to unite this vast and talented community in the digital age, this portal was created to bring our people together. Whether it's finding a life partner, growing a business, finding a job, or donating blood to save a life—this platform is by the Samaj, for the Samaj.
            </p>
        </div>
    </div>
</div>
HTML;

$stmt = $pdo->prepare("UPDATE pages SET content = ? WHERE slug = 'about'");
$stmt->execute([$new_content]);
echo "Updated CMS content in DB.";
?>
