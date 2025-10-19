
document.addEventListener('DOMContentLoaded', function() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    const today = new Date().toISOString().split('T')[0];
    
    dateInputs.forEach(input => {
        if (!input.hasAttribute('min')) {
            input.setAttribute('min', today);
        }
    });
    
    const originSelect = document.getElementById('origin');
    const destinationSelect = document.getElementById('destination');
    
    if (originSelect && destinationSelect) {
        originSelect.addEventListener('change', function() {
            if (this.value === destinationSelect.value && this.value !== '') {
                alert('Kalkış ve varış noktası aynı olamaz!');
                this.value = '';
            }
        });
        
        destinationSelect.addEventListener('change', function() {
            if (this.value === originSelect.value && this.value !== '') {
                alert('Kalkış ve varış noktası aynı olamaz!');
                this.value = '';
            }
        });
    }
    
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

function confirmDelete(message) {
    return confirm(message || 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?');
}

function toggleSeat(seatNumber, isBooked) {
    if (isBooked) {
        return false;
    }
    
    const seatElement = document.querySelector(`[data-seat="${seatNumber}"]`);
    if (seatElement) {
        seatElement.classList.toggle('selected');
        updateSelectedSeats();
    }
}

function updateSelectedSeats() {
    const selectedSeats = document.querySelectorAll('.seat.selected');
    const seatNumbersInput = document.getElementById('selected_seats');
    const seatCountElement = document.getElementById('seat_count');
    
    if (seatNumbersInput) {
        const seatNumbers = Array.from(selectedSeats).map(seat => seat.dataset.seat);
        seatNumbersInput.value = seatNumbers.join(',');
    }
    
    if (seatCountElement) {
        seatCountElement.textContent = selectedSeats.length;
    }
}

function formatMoney(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY'
    }).format(amount);
}

function showLoading() {
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'loading-overlay';
    loadingDiv.innerHTML = '<div class="loading-spinner"></div>';
    document.body.appendChild(loadingDiv);
}

function hideLoading() {
    const loadingDiv = document.querySelector('.loading-overlay');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}