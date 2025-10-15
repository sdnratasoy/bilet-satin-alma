</div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="nav-brand">
                <a href="/index.php">
                    <img src="/assets/img/bus-icon.png" alt="RoadFinder" class="brand-icon">
                    RoadFinder
                </a>
            </div>
                    <p>Güvenli ve kolay bilet satın alma platformu</p>
                </div>
                <div class="footer-section">
                    <h4>Hızlı Linkler</h4>
                    <ul>
                        <li><a href="/index.php">Ana Sayfa</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="/user/tickets.php">Biletlerim</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>İletişim</h4>
                    <p>Email: info@roadfinder.com</p>
                    <p>Tel: +90 (555) 123 45 67</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 RoadFinder. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </footer>
    
    <script src="/assets/js/script.js"></script>
</body>
</html>