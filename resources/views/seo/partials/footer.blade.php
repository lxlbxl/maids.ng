<footer class="seo-footer">
    <div class="container">
        <div class="seo-footer-grid">
            <div>
                <img src="{{ asset('maids-logo.png') }}" alt="Maids.ng" style="height:32px;filter:brightness(0) invert(1);margin-bottom:12px;">
                <p style="font-size:13px;">Nigeria's most trusted platform for verified domestic helpers.</p>
            </div>
            <div>
                <h4>Platform</h4>
                <ul class="footer-links">
                    <li><a href="{{ url('/onboarding') }}">Find a Helper</a></li>
                    <li><a href="{{ url('/register/maid') }}">Become a Helper</a></li>
                    <li><a href="{{ url('/maids') }}">Browse Helpers</a></li>
                </ul>
            </div>
            <div>
                <h4>SEO Pages</h4>
                <ul class="footer-links">
                    <li><a href="{{ route('seo.locations') }}">Locations</a></li>
                    <li><a href="{{ url('/find/housekeeper/') }}">Services</a></li>
                    <li><a href="{{ url('/faq/') }}">FAQ</a></li>
                </ul>
            </div>
            <div>
                <h4>Company</h4>
                <ul class="footer-links">
                    <li><a href="{{ url('/about') }}">About Us</a></li>
                    <li><a href="{{ url('/contact') }}">Contact</a></li>
                    <li><a href="{{ url('/blog') }}">Blog</a></li>
                    <li><a href="{{ url('/terms') }}">Terms</a></li>
                    <li><a href="{{ url('/privacy') }}">Privacy</a></li>
                </ul>
            </div>
        </div>
        <div class="seo-footer-bottom">
            <p>&copy; {{ date('Y') }} Maids.ng. All rights reserved.</p>
        </div>
    </div>
</footer>
