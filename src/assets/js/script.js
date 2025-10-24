
function createToastContainer() {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    return container;
}

function showToast(message, type = 'success') {
    const container = createToastContainer();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const icon = type === 'success' ? '✅' : '❌';

    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">${message}</div>
        <button class="toast-close" onclick="closeToast(this)">×</button>
        <div class="toast-progress"></div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        closeToast(toast.querySelector('.toast-close'));
    }, 5000);
}

function closeToast(button) {
    const toast = button.parentElement || button;
    toast.classList.add('hiding');
    setTimeout(() => {
        toast.remove();

        const container = document.querySelector('.toast-container');
        if (container && container.children.length === 0) {
            container.remove();
        }
    }, 300);
}

document.addEventListener('DOMContentLoaded', function() {
    const toastMessages = document.querySelectorAll('.toast-message');
    toastMessages.forEach(msg => {
        const type = msg.dataset.type;
        const message = msg.dataset.message;
        if (message) {
            showToast(message, type);
        }
    });

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
                showToast('Kalkış ve varış noktası aynı olamaz!', 'error');
                this.value = '';
            }
        });

        destinationSelect.addEventListener('change', function() {
            if (this.value === originSelect.value && this.value !== '') {
                showToast('Kalkış ve varış noktası aynı olamaz!', 'error');
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

window.showCancelModal = function(ticketId, price) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';

    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon warning">⚠️</div>
                <h2 class="modal-title">Bilet İptali</h2>
            </div>
            <div class="modal-body">
                <p>Bu bileti iptal etmek istediğinizden emin misiniz?</p>
                <div class="modal-highlight">
                    💰 İade Edilecek Tutar: <strong>${price}</strong>
                </div>
                <p style="margin-top: 1rem; color: var(--text-light); font-size: 0.9rem;">
                    Bilet ücreti hesabınıza iade edilecektir.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-cancel-modal" onclick="window.closeModal()">
                    Vazgeç
                </button>
                <button class="btn btn-confirm-cancel" onclick="window.confirmCancel(${ticketId})">
                    İptal Et
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    document.addEventListener('keydown', window.handleEscapeKey);

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            window.closeModal();
        }
    });
};

window.handleEscapeKey = function(e) {
    if (e.key === 'Escape') {
        window.closeModal();
    }
};

window.closeModal = function() {
    const overlay = document.querySelector('.modal-overlay');
    if (overlay) {
        overlay.classList.add('hiding');
        setTimeout(() => {
            overlay.remove();
            document.removeEventListener('keydown', window.handleEscapeKey);
        }, 200);
    }
};

window.confirmCancel = function(ticketId) {
    window.location.href = `/user/cancel-ticket.php?id=${ticketId}`;
};