<!-- software-booking-form.html -->
<form id="software-form" method="POST" action="https://your-laravel-app.com/api/leads/software">
    <input type="hidden" name="brand_slug" value="vumbi-ventures">
    
    <h3>💻 Hire for Software Engineering</h3>
    
    <div class="form-group">
        <label>First Name *</label>
        <input type="text" name="first_name" required>
    </div>
    
    <div class="form-group">
        <label>Last Name *</label>
        <input type="text" name="last_name" required>
    </div>
    
    <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" required>
    </div>
    
    <div class="form-group">
        <label>Phone</label>
        <input type="tel" name="phone">
    </div>
    
    <div class="form-group">
        <label>Company</label>
        <input type="text" name="company" placeholder="Your company name">
    </div>
    
    <div class="form-group">
        <label>Project Type *</label>
        <select name="project_type" required>
            <option value="">Select...</option>
            <option value="web">🌐 Web Development</option>
            <option value="mobile">📱 Mobile App</option>
            <option value="api">🔌 API Development</option>
            <option value="custom">⚙️ Custom Software</option>
            <option value="seo">🔍 SEO & Digital Marketing</option>
            <option value="consulting">💡 Technical Consulting</option>
        </select>
    </div>
    
    <div class="form-group">
        <label>Project Description *</label>
        <textarea name="project_description" rows="4" required placeholder="Describe your project in detail..."></textarea>
    </div>
    
    <div class="form-group">
        <label>Expected Timeline</label>
        <input type="text" name="timeline" placeholder="e.g., 2 months, Q4 2026">
    </div>
    
    <div class="form-group">
        <label>Budget Range</label>
        <input type="text" name="budget" placeholder="e.g., $5,000 - $10,000">
    </div>
    
    <div class="form-group">
        <label>Current Tech Stack</label>
        <input type="text" name="current_stack" placeholder="e.g., PHP, MySQL, React">
    </div>
    
    <div class="form-group">
        <label>Team Size</label>
        <input type="number" name="team_size" min="1" placeholder="Number of developers needed">
    </div>
    
    <div class="form-group">
        <label>Additional Details</label>
        <textarea name="message" rows="3" placeholder="Any other information?"></textarea>
    </div>
    
    <button type="submit" class="btn-primary">📩 Request Software Engineering</button>
</form>

// forms.js
document.addEventListener('DOMContentLoaded', function() {
    // Travel Form
    const travelForm = document.getElementById('travel-form');
    if (travelForm) {
        travelForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await fetch('https://your-laravel-app.com/api/leads/travel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('🎉 Thank you! Your travel booking request has been received. We\'ll get back to you within 24 hours.');
                    this.reset();
                } else {
                    showError('⚠️ Something went wrong. Please try again or contact us directly.');
                }
            } catch (error) {
                console.error('Error:', error);
                showError('⚠️ Network error. Please check your connection and try again.');
            }
        });
    }

    // Software Form
    const softwareForm = document.getElementById('software-form');
    if (softwareForm) {
        softwareForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            try {
                const response = await fetch('https://your-laravel-app.com/api/leads/software', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('🎉 Thank you! Your software request has been received. We\'ll get back to you within 24 hours.');
                    this.reset();
                } else {
                    showError('⚠️ Something went wrong. Please try again or contact us directly.');
                }
            } catch (error) {
                console.error('Error:', error);
                showError('⚠️ Network error. Please check your connection and try again.');
            }
        });
    }

    function showSuccess(message) {
        const el = document.getElementById('success-message');
        if (el) {
            el.textContent = message;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 5000);
        }
    }

    function showError(message) {
        const el = document.getElementById('error-message');
        if (el) {
            el.textContent = message;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 5000);
        }
    }
});