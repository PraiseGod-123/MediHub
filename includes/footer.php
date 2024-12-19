</div><!-- Close main container -->
</main>

<footer class="footer">
    <div class="container">
        <div class="footer-container">
            <div class="footer-col">
                <h3>MediHub</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>/about.php" class="footer-link">About Us</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/contact.php" class="footer-link">Contact</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/privacy.php" class="footer-link">Privacy Policy</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/terms.php" class="footer-link">Terms of Service</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>For Customers</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>/customer/medicines.php" class="footer-link">Browse Medicines</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/customer/orders.php" class="footer-link">Track Orders</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/faqs.php" class="footer-link">FAQs</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/support.php" class="footer-link">Customer Support</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>For Pharmacies</h3>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>/pharmacy/register.php" class="footer-link">Join as Pharmacy</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pharmacy/guidelines.php" class="footer-link">Seller Guidelines</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pharmacy/support.php" class="footer-link">Seller Support</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pharmacy/success.php" class="footer-link">Success Stories</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Connect With Us</h3>
                <ul class="footer-links">
                    <li><a href="#" class="footer-link">Subscribe to Newsletter</a></li>
                    <li>
                        <div class="social-links">
                            <a href="#" class="social-link" aria-label="Facebook">
                                <i class="fab fa-facebook"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="LinkedIn">
                                <i class="fab fa-linkedin"></i>
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> MediHub. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Mobile Menu JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.querySelector('.menu-toggle');
        const navMenu = document.querySelector('.nav-menu');

        menuToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            menuToggle.querySelector('i').classList.toggle('fa-bars');
            menuToggle.querySelector('i').classList.toggle('fa-times');
        });

        // Handle notifications
        document.querySelectorAll('.notification').forEach(notification => {
            // Auto-hide after 5 seconds
            setTimeout(() => {
                hideNotification(notification);
            }, 5000);

            // Handle close button
            notification.querySelector('.notification-close').addEventListener('click', () => {
                hideNotification(notification);
            });
        });

        function hideNotification(notification) {
            notification.classList.add('hiding');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }
    });
</script>

</body>

</html>