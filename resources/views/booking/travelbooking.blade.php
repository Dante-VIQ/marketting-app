<!-- travel-booking-form.html -->
<form id="travel-form" method="POST" action="https://your-laravel-app.com/api/leads/travel">
    <input type="hidden" name="brand_slug" value="vumbi-ventures">
    
    <h3>🌍 Book Your Safari or Tour</h3>
    
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
        <label>Tour Type</label>
        <select name="tour_type">
            <option value="">Select...</option>
            <option value="safari">🦁 Safari Tour</option>
            <option value="beach">🏖️ Beach Holiday</option>
            <option value="cultural">🎭 Cultural Experience</option>
            <option value="adventure">🧗 Adventure Travel</option>
            <option value="luxury">✨ Luxury Travel</option>
            <option value="custom">📝 Custom Package</option>
        </select>
    </div>
    
    <div class="form-group">
        <label>Number of People</label>
        <input type="number" name="number_of_people" min="1" value="1">
    </div>
    
    <div class="form-group">
        <label>Preferred Date</label>
        <input type="date" name="preferred_date">
    </div>
    
    <div class="form-group">
        <label>Duration (Days)</label>
        <input type="number" name="duration_days" min="1" placeholder="e.g., 7">
    </div>
    
    <div class="form-group">
        <label>Budget Range</label>
        <input type="text" name="budget_range" placeholder="e.g., $1,000 - $3,000">
    </div>
    
    <div class="form-group">
        <label>Destination Country</label>
        <input type="text" name="country" placeholder="e.g., Kenya, Tanzania">
    </div>
    
    <div class="form-group">
        <label>Special Requests</label>
        <textarea name="special_requests" rows="2" placeholder="Any special requirements?"></textarea>
    </div>
    
    <div class="form-group">
        <label>Additional Details</label>
        <textarea name="message" rows="3" placeholder="Anything else we should know?"></textarea>
    </div>
    
    <button type="submit" class="btn-primary">📩 Request Travel Booking</button>
</form>