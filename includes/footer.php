</div> <!-- Close container-fluid from header -->
    
    <!-- Footer -->
    <footer class="bg-dark text-white mt-5">
        <div class="container-fluid py-4">
            <div class="row">
                <!-- Company Info -->
                <div class="col-md-4 mb-3 mb-md-0">
                    <h5><i class="fas fa-building"></i> HRMS</h5>
                    <p class="text-muted mb-2">Human Resource Management System</p>
                    <p class="small text-muted">Streamlining HR operations with modern technology</p>
                </div>
                
                <!-- Quick Links -->
                <div class="col-md-4 mb-3 mb-md-0">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($user_role === ROLE_ADMIN): ?>
                                <li><a href="/hrms/admin/index.php" class="text-muted text-decoration-none"><i class="fas fa-angle-right"></i> Dashboard</a></li>
                                <li><a href="/hrms/admin/employees.php" class="text-muted text-decoration-none"><i class="fas fa-angle-right"></i> Employees</a></li>
                                <li><a href="/hrms/admin/reports.php" class="text-muted text-decoration-none"><i class="fas fa-angle-right"></i> Reports</a></li>
                            <?php else: ?>
                                <li><a href="/hrms/employee/index.php" class="text-muted text-decoration-none"><i class="fas fa-angle-right"></i> Dashboard</a></li>
                                <li><a href="/hrms/employee/attendance.php" class="text-muted text-decoration-none"><i class="fas fa-angle-right"></i> Attendance</a></li>
                                <li><a href="/hrms/employee/leave-request.php" class="text-muted text-decoration-none"><i class="fas fa-angle-right"></i> Leave Request</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Contact & Support -->
                <div class="col-md-4">
                    <h6>Support</h6>
                    <ul class="list-unstyled text-muted small">
                        <li><i class="fas fa-envelope"></i> support@hrms.com</li>
                        <li><i class="fas fa-phone"></i> +1 (555) 123-4567</li>
                        <li class="mt-2">
                            <a href="#" class="text-muted text-decoration-none me-2">Help Center</a>
                            <a href="#" class="text-muted text-decoration-none">Contact Us</a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr class="bg-secondary">
            
            <!-- Bottom Footer -->
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <small class="text-muted">
                        © <?php echo date('Y'); ?> HRMS. All rights reserved.
                    </small>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <small>
                        <a href="#" class="text-muted text-decoration-none me-2">Privacy Policy</a>
                        <a href="#" class="text-muted text-decoration-none me-2">Terms of Service</a>
                        <a href="#" class="text-muted text-decoration-none">Cookie Policy</a>
                    </small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle -->
    <script src="/hrms/assets/js/jquery.min.js"></script>
    <script src="/hrms/assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/hrms/assets/js/script.js"></script>
    
    <!-- Additional Scripts for specific pages -->
    <?php if (isset($additional_scripts)): ?>
        <?php echo $additional_scripts; ?>
    <?php endif; ?>
</body>
</html>
