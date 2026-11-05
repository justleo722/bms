@extends('layouts.master', ['activeNav' => $activeNav ?? 'rooms'])

@section('title', 'Rooms')

@push('scripts')
<script>
window.roomsData = function (initialState, permissions, todayDate, roomTypes) {
    return {
        activeTab: 'rooms',
        statusFilter: 'all',
        showCheckInModal: false,
        showBookingModal: false,
        showStayModal: false,
        showCheckoutModal: false,
        showExpenseModal: false,
        showRoomModal: false,
        showDeleteRoomModal: false,
        showCancelReservationModal: false,
        showConfirmModal: false,
        confirmTitle: '',
        confirmMessage: '',
        confirmLabel: 'Delete',
        confirmAction: null,
        selectedRoomId: null,
        editingRoomId: null,
        roomToDelete: null,
        reservationToCancel: null,
        incomeDateFrom: '',
        incomeDateTo: '',
        saving: false,
        actionError: '',
        notice: '',
        noticeTimer: null,
        todayDate: todayDate,
        roomTypes: roomTypes || [],
        can: permissions || {},
        checkInForm: { guest: '', phone: '', guests: 1, checkInAt: '', expectedCheckoutAt: '', rate: 85000, deposit: 0 },
        bookingForm: { roomId: '', guest: '', phone: '', guests: 1, checkInDate: '', nights: 1, rate: 85000, deposit: 0 },
        chargeForm: { description: '', amount: '' },
        paymentForm: { method: 'Cash', amount: '' },
        checkoutForm: { paymentMethod: 'Cash', amountReceived: 0, checkoutAt: '' },
        expenseForm: { description: '', category: 'Supplies', amount: '', date: todayDate, roomId: '' },
        roomForm: { number: '', type: 'Standard', floor: 'Ground floor', rate: 85000, capacity: 2, amenities: '', status: 'available', notes: '' },
        rooms: (initialState && initialState.rooms) || [],
        reservations: (initialState && initialState.reservations) || [],
        incomePayments: (initialState && initialState.incomePayments) || [],
        roomExpenses: (initialState && initialState.roomExpenses) || [],
        completedStays: (initialState && initialState.completedStays) || [],
        revision: '',
        liveTimer: null,

        init() {
            var self = this;
            this.liveTimer = setInterval(function () { self.refreshBoard(); }, 15000);
        },

        refreshBoard() {
            if (document.hidden || this.saving) return;
            if (this.showCheckInModal || this.showBookingModal || this.showStayModal || this.showCheckoutModal
                || this.showExpenseModal || this.showRoomModal || this.showDeleteRoomModal
                || this.showCancelReservationModal || this.showConfirmModal) {
                return;
            }

            var self = this;
            fetch('/rooms/board', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (payload) {
                    if (!payload || !payload.data || payload.revision === self.revision) return;
                    self.revision = payload.revision;
                    self.rooms = payload.data.rooms || [];
                    self.reservations = payload.data.reservations || [];
                    if (self.selectedRoomId && !self.selectedRoom) {
                        self.selectedRoomId = null;
                        self.showStayModal = false;
                        self.showCheckoutModal = false;
                    }
                })
                .catch(function () {
                    // Ignore background refresh failures; the board keeps its last good state.
                });
        },

        get selectedRoom() {
            var id = this.selectedRoomId;
            return this.rooms.find(function (room) { return room.id === id; }) || null;
        },
        get filteredRooms() {
            var filter = this.statusFilter;
            return this.rooms.filter(function (room) { return filter === 'all' || room.status === filter; });
        },
        get availableCount() { return this.rooms.filter(function (room) { return room.status === 'available'; }).length; },
        get occupiedCount() { return this.rooms.filter(function (room) { return room.status === 'occupied'; }).length; },
        get reservedCount() { return this.rooms.filter(function (room) { return room.status === 'reserved'; }).length; },
        get housekeepingCount() { return this.rooms.filter(function (room) { return room.status === 'cleaning'; }).length; },
        get maintenanceCount() { return this.rooms.filter(function (room) { return room.status === 'maintenance'; }).length; },
        get occupancyPercent() { return this.rooms.length ? Math.round(this.occupiedCount / this.rooms.length * 100) : 0; },
        get upcomingReservations() { return this.reservations.filter(function (reservation) { return reservation.status === 'confirmed'; }); },
        inIncomeDateRange(date) {
            if (this.incomeDateFrom && this.incomeDateTo && this.incomeDateFrom > this.incomeDateTo) return false;
            return (!this.incomeDateFrom || date >= this.incomeDateFrom)
                && (!this.incomeDateTo || date <= this.incomeDateTo);
        },
        get filteredIncomePayments() {
            var self = this;
            return this.incomePayments.filter(function (payment) { return self.inIncomeDateRange(payment.date); });
        },
        get filteredRoomExpenses() {
            var self = this;
            return this.roomExpenses.filter(function (expense) { return self.inIncomeDateRange(expense.date); });
        },
        get totalIncome() { return this.filteredIncomePayments.reduce(function (sum, payment) { return sum + Number(payment.amount); }, 0); },
        get totalExpenses() { return this.filteredRoomExpenses.reduce(function (sum, expense) { return sum + Number(expense.amount); }, 0); },
        get netProfit() { return this.totalIncome - this.totalExpenses; },
        get dailyFinancials() {
            var days = {};
            this.filteredIncomePayments.forEach(function (payment) {
                if (!days[payment.date]) days[payment.date] = { date: payment.date, income: 0, expenses: 0 };
                days[payment.date].income += Number(payment.amount);
            });
            this.filteredRoomExpenses.forEach(function (expense) {
                if (!days[expense.date]) days[expense.date] = { date: expense.date, income: 0, expenses: 0 };
                days[expense.date].expenses += Number(expense.amount);
            });
            return Object.keys(days).sort().reverse().map(function (date) {
                days[date].profit = days[date].income - days[date].expenses;
                return days[date];
            });
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },
        apiError(error, fallback) {
            var payload = error && error.response ? error.response.data : null;
            if (payload) {
                if (payload.errors) {
                    var first = Object.values(payload.errors).flat()[0];
                    if (first) return first;
                }
                if (payload.message) return payload.message;
            }
            return fallback || 'Something went wrong. Please try again.';
        },
        showNotice(message) {
            var self = this;
            this.notice = message || '';
            clearTimeout(this.noticeTimer);
            if (message) {
                this.noticeTimer = setTimeout(function () { self.notice = ''; }, 4500);
            }
        },
        applyState(data) {
            if (!data) return;
            this.rooms = data.rooms || [];
            this.reservations = data.reservations || [];
            this.incomePayments = data.incomePayments || [];
            this.roomExpenses = data.roomExpenses || [];
            this.completedStays = data.completedStays || [];

            if (this.selectedRoomId && !this.selectedRoom) {
                this.selectedRoomId = null;
                this.showStayModal = false;
                this.showCheckoutModal = false;
            }
        },
        saveAction(method, url, payload, fallbackError) {
            if (this.saving) return Promise.resolve(null);
            var self = this;
            this.saving = true;
            this.actionError = '';
            return window.axios({
                method: method,
                url: url,
                data: payload || {},
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken()
                }
            }).then(function (response) {
                if (response.data && response.data.data) self.applyState(response.data.data);
                self.showNotice(response.data.message || 'Saved successfully.');
                return response.data;
            }).catch(function (error) {
                self.actionError = self.apiError(error, fallbackError);
                return null;
            }).then(function (data) {
                self.saving = false;
                return data;
            });
        },

        statusLabel(status) {
            return { available: 'Available', occupied: 'Occupied', reserved: 'Reserved', cleaning: 'Needs cleaning', maintenance: 'Maintenance' }[status] || status;
        },
        statusClasses(status) {
            return {
                available: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                occupied: 'bg-blue-50 text-blue-700 ring-blue-600/20',
                reserved: 'bg-amber-50 text-amber-700 ring-amber-600/20',
                cleaning: 'bg-orange-50 text-orange-700 ring-orange-600/20',
                maintenance: 'bg-gray-100 text-gray-600 ring-gray-500/20'
            }[status] || 'bg-gray-100 text-gray-600 ring-gray-500/20';
        },
        formatMoney(amount) {
            return (window.BmsMoney ? window.BmsMoney.symbol : 'TSh') + ' ' + Number(amount || 0).toLocaleString();
        },
        roomForReservation(reservation) {
            return this.rooms.filter(function (room) { return room.id === reservation.roomId; })[0] || null;
        },
        localDateTime(date) {
            var value = date || new Date();
            var offset = value.getTimezoneOffset() * 60000;
            return new Date(value.getTime() - offset).toISOString().slice(0, 16);
        },
        localDate(date) {
            return this.localDateTime(date).slice(0, 10);
        },
        daysStayed(room, endAt) {
            if (!room || !room.stay || !room.stay.checkInAt) return 0;
            var start = new Date(room.stay.checkInAt).getTime();
            var end = new Date(endAt || new Date()).getTime();
            if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return 1;
            return Math.max(1, Math.ceil((end - start) / 86400000));
        },
        checkInNights() {
            var start = new Date(this.checkInForm.checkInAt).getTime();
            var end = new Date(this.checkInForm.expectedCheckoutAt).getTime();
            if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return 0;
            return Math.max(1, Math.ceil((end - start) / 86400000));
        },
        checkInEstimate() {
            return Number(this.checkInForm.rate || 0) * this.checkInNights();
        },
        stayCharges(room, endAt) {
            if (!room || !room.stay) return 0;
            var roomCharge = Number(room.stay.rate) * this.daysStayed(room, endAt);
            var extraCharges = room.stay.charges.reduce(function (sum, charge) { return sum + Number(charge.amount); }, 0);
            return roomCharge + extraCharges;
        },
        stayPayments(room) {
            if (!room || !room.stay) return 0;
            return room.stay.payments.reduce(function (sum, payment) { return sum + Number(payment.amount); }, 0);
        },
        stayTotal(room) {
            return Math.max(0, this.stayCharges(room) - this.stayPayments(room));
        },

        openRoomModal(room) {
            this.actionError = '';
            this.editingRoomId = room ? room.id : null;
            this.roomForm = room ? {
                number: room.number,
                type: room.type,
                floor: room.floor || '',
                rate: Number(room.rate),
                capacity: Number(room.capacity),
                amenities: room.amenities || '',
                status: room.status,
                notes: room.notes || ''
            } : {
                number: '',
                type: this.roomTypes[0] || 'Standard',
                floor: 'Ground floor',
                rate: 85000,
                capacity: 2,
                amenities: '',
                status: 'available',
                notes: ''
            };
            this.showRoomModal = true;
        },
        saveRoom() {
            var self = this;
            var editing = this.editingRoomId;
            var method = editing ? 'put' : 'post';
            var url = editing ? '/rooms/' + editing : '/rooms';
            this.saveAction(method, url, this.roomForm, 'Could not save this room.').then(function (data) {
                if (data) {
                    self.showRoomModal = false;
                    self.editingRoomId = null;
                }
            });
        },
        askDeleteRoom(room) {
            this.roomToDelete = room;
            this.actionError = '';
            this.showDeleteRoomModal = true;
        },
        confirmDeleteRoom() {
            if (!this.roomToDelete) return;
            var self = this;
            var room = this.roomToDelete;
            this.saveAction('delete', '/rooms/' + room.id, {}, 'Could not delete this room.').then(function (data) {
                if (data) {
                    self.showDeleteRoomModal = false;
                    self.roomToDelete = null;
                    self.showRoomModal = false;
                }
            });
        },

        openCheckIn(room) {
            if (!room) return;
            var checkInAt = this.localDateTime(new Date());
            var checkout = new Date();
            checkout.setDate(checkout.getDate() + 1);
            checkout.setHours(11, 0, 0, 0);
            this.actionError = '';
            this.selectedRoomId = room.id;
            this.checkInForm = { guest: '', phone: '', guests: 1, checkInAt: checkInAt, expectedCheckoutAt: this.localDateTime(checkout), rate: Number(room.rate), deposit: 0 };
            this.showCheckInModal = true;
        },
        checkInGuest() {
            var room = this.selectedRoom;
            if (!room || this.saving) return;
            var self = this;
            var form = this.checkInForm;
            this.saveAction('post', '/rooms/' + room.id + '/check-in', {
                guest: form.guest,
                phone: form.phone,
                guests: Number(form.guests),
                check_in_at: form.checkInAt,
                expected_checkout_at: form.expectedCheckoutAt,
                rate: Number(form.rate),
                deposit: Number(form.deposit || 0)
            }, 'Could not check this guest in.').then(function (data) {
                if (data) {
                    self.showCheckInModal = false;
                    self.selectedRoomId = null;
                }
            });
        },
        openStay(room) {
            this.actionError = '';
            this.selectedRoomId = room.id;
            this.chargeForm = { description: '', amount: '' };
            this.paymentForm = { method: 'Cash', amount: '' };
            this.showStayModal = true;
        },
        addStayCharge() {
            var room = this.selectedRoom;
            if (!room || !room.stay || this.saving) return;
            var self = this;
            var stay = room.stay;
            this.saveAction('post', '/rooms/stays/' + stay.id + '/charges', {
                description: this.chargeForm.description,
                amount: Number(this.chargeForm.amount)
            }, 'Could not add this charge.').then(function (data) {
                if (data) self.chargeForm = { description: '', amount: '' };
            });
        },
        removeStayCharge(chargeId) {
            var room = this.selectedRoom;
            if (!room || !room.stay || this.saving) return;
            this.saveAction('delete', '/rooms/stays/' + room.stay.id + '/charges/' + chargeId, {}, 'Could not remove this charge.');
        },
        askRemoveStayCharge(charge) {
            var self = this;
            this.askConfirm('Remove folio charge', 'Remove "' + charge.description + '" from this guest folio?', 'Remove charge', function () {
                self.removeStayCharge(charge.id);
            });
        },
        addStayPayment() {
            var room = this.selectedRoom;
            if (!room || !room.stay || this.saving) return;
            var self = this;
            var stay = room.stay;
            this.saveAction('post', '/rooms/stays/' + stay.id + '/payments', {
                amount: Number(this.paymentForm.amount),
                method: this.paymentForm.method
            }, 'Could not record this payment.').then(function (data) {
                if (data) self.paymentForm = { method: 'Cash', amount: '' };
            });
        },
        openCheckout(room) {
            this.actionError = '';
            this.selectedRoomId = room.id;
            var checkoutAt = this.localDateTime(new Date());
            this.checkoutForm = { paymentMethod: 'Cash', amountReceived: Math.max(0, this.stayCharges(room, checkoutAt) - this.stayPayments(room)), checkoutAt: checkoutAt };
            this.showStayModal = false;
            this.showCheckoutModal = true;
        },
        checkoutBalance() {
            if (!this.selectedRoom || !this.checkoutForm.checkoutAt) return 0;
            return Math.max(0, this.stayCharges(this.selectedRoom, this.checkoutForm.checkoutAt) - this.stayPayments(this.selectedRoom));
        },
        checkoutChange() {
            return Math.max(0, Number(this.checkoutForm.amountReceived) - this.checkoutBalance());
        },
        checkOutGuest() {
            var room = this.selectedRoom;
            if (!room || !room.stay || this.saving) return;
            var self = this;
            var stay = room.stay;
            this.saveAction('post', '/rooms/stays/' + stay.id + '/checkout', {
                checkout_at: this.checkoutForm.checkoutAt,
                payment_method: this.checkoutForm.paymentMethod,
                amount_received: Number(this.checkoutForm.amountReceived)
            }, 'Could not complete this checkout.').then(function (data) {
                if (data) {
                    self.showCheckoutModal = false;
                    self.selectedRoomId = null;
                    self.activeTab = 'housekeeping';
                }
            });
        },

        openBooking(room) {
            var defaultRoom = room || this.rooms.filter(function (item) { return item.status === 'available'; })[0] || null;
            var date = new Date();
            date.setDate(date.getDate() + 1);
            this.actionError = '';
            this.selectedRoomId = room ? room.id : null;
            this.bookingForm = {
                roomId: room ? String(room.id) : '',
                guest: '',
                phone: '',
                guests: 1,
                checkInDate: date.toISOString().slice(0, 10),
                nights: 1,
                rate: defaultRoom ? Number(defaultRoom.rate) : 85000,
                deposit: 0
            };
            this.showBookingModal = true;
        },
        createReservation() {
            if (this.saving) return;
            var self = this;
            var form = this.bookingForm;
            this.saveAction('post', '/rooms/reservations', {
                room_id: Number(form.roomId),
                guest: form.guest,
                phone: form.phone,
                check_in_date: form.checkInDate,
                nights: Number(form.nights),
                guests: Number(form.guests),
                rate: Number(form.rate),
                deposit: Number(form.deposit || 0)
            }, 'Could not save this reservation.').then(function (data) {
                if (data) {
                    self.showBookingModal = false;
                    self.activeTab = 'reservations';
                }
            });
        },
        checkInReservation(reservation) {
            if (this.saving) return;
            var self = this;
            this.saveAction('post', '/rooms/reservations/' + reservation.id + '/check-in', {}, 'Could not check this guest in.').then(function (data) {
                if (data) {
                    self.selectedRoomId = null;
                }
            });
        },
        askCancelReservation(reservation) {
            this.reservationToCancel = reservation;
            this.actionError = '';
            this.showCancelReservationModal = true;
        },
        confirmCancelReservation() {
            if (!this.reservationToCancel || this.saving) return;
            var self = this;
            var reservation = this.reservationToCancel;
            this.saveAction('post', '/rooms/reservations/' + reservation.id + '/cancel', {}, 'Could not cancel this reservation.').then(function (data) {
                if (data) {
                    self.showCancelReservationModal = false;
                    self.reservationToCancel = null;
                }
            });
        },

        markRoomReady(room) {
            if (!room || this.saving) return;
            this.saveAction('post', '/rooms/' + room.id + '/ready', {}, 'Could not update this room.');
        },
        openExpenseModal() {
            this.actionError = '';
            this.showExpenseModal = true;
        },
        addRoomExpense() {
            if (this.saving) return;
            var self = this;
            var form = this.expenseForm;
            this.saveAction('post', '/rooms/expenses', {
                description: form.description,
                category: form.category,
                amount: Number(form.amount),
                expense_date: form.date,
                room_id: form.roomId || null
            }, 'Could not save this room expense.').then(function (data) {
                if (data) {
                    self.expenseForm = { description: '', category: 'Supplies', amount: '', date: self.todayDate, roomId: '' };
                    self.showExpenseModal = false;
                }
            });
        },
        removeRoomExpense(id) {
            if (this.saving) return;
            this.saveAction('delete', '/rooms/expenses/' + id, {}, 'Could not delete this room expense.');
        },
        askRemoveRoomExpense(expense) {
            var self = this;
            this.askConfirm('Delete room expense', 'Delete "' + expense.description + '"?', 'Delete expense', function () {
                self.removeRoomExpense(expense.id);
            });
        },
        askConfirm(title, message, label, action) {
            this.confirmTitle = title;
            this.confirmMessage = message;
            this.confirmLabel = label || 'Delete';
            this.confirmAction = action;
            this.actionError = '';
            this.showConfirmModal = true;
        },
        runConfirm() {
            var action = this.confirmAction;
            this.showConfirmModal = false;
            this.confirmAction = null;
            if (action) action();
        }
    };
};
</script>
@endpush

@section('content')
<div x-data="roomsData(@js($roomsState), @js($roomPermissions), @js($roomsToday), @js($roomTypes))" class="pb-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Rooms</h1>
            <p class="mt-1 text-sm text-gray-500">Manage lodge reservations, guest stays, payments and room readiness.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button x-show="can.manage" @click="openRoomModal()" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                @heroicon('o-plus', 'h-4 w-4')
                Add room
            </button>
            <button x-show="can.reservations" @click="openBooking()" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                @heroicon('o-calendar-days', 'h-4 w-4')
                New reservation
            </button>
            <button x-show="can.checkIn" @click="openCheckIn(rooms.filter(function (room) { return room.status === 'available'; })[0])" :disabled="availableCount === 0" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                @heroicon('o-arrow-right-on-rectangle', 'h-4 w-4')
                Check in guest
            </button>
        </div>
    </div>

    <div x-show="notice" x-cloak class="mb-4 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
        @heroicon('o-check-circle', 'h-5 w-5 flex-shrink-0')
        <span x-text="notice"></span>
    </div>
    <div x-show="actionError" x-cloak class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
        @heroicon('o-exclamation-circle', 'h-5 w-5 flex-shrink-0')
        <span x-text="actionError"></span>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-6">
        <button @click="activeTab = 'rooms'; statusFilter = 'all'" class="rounded-xl border border-gray-200 bg-white p-4 text-left shadow-sm hover:border-gray-300">
            <p class="text-xs font-medium text-gray-500">Total rooms</p>
            <p class="mt-1 text-2xl font-bold text-gray-900" x-text="rooms.length"></p>
        </button>
        <button @click="activeTab = 'rooms'; statusFilter = 'available'" class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 text-left hover:border-emerald-200">
            <p class="text-xs font-medium text-emerald-700">Available</p>
            <p class="mt-1 text-2xl font-bold text-emerald-800" x-text="availableCount"></p>
        </button>
        <button @click="activeTab = 'rooms'; statusFilter = 'occupied'" class="rounded-xl border border-blue-100 bg-blue-50/60 p-4 text-left hover:border-blue-200">
            <p class="text-xs font-medium text-blue-700">Guests staying</p>
            <p class="mt-1 text-2xl font-bold text-blue-800" x-text="occupiedCount"></p>
        </button>
        <button @click="activeTab = 'housekeeping'; statusFilter = 'all'" class="rounded-xl border border-orange-100 bg-orange-50/60 p-4 text-left hover:border-orange-200">
            <p class="text-xs font-medium text-orange-700">Need attention</p>
            <p class="mt-1 text-2xl font-bold text-orange-800" x-text="housekeepingCount + rooms.filter(function (room) { return room.status === 'maintenance'; }).length"></p>
        </button>
    </div>

    <div class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="mb-2 flex items-center justify-between text-sm">
            <span class="font-semibold text-gray-700">Room occupancy</span>
            <span class="font-bold text-gray-900" x-text="occupancyPercent + '%'"></span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full bg-blue-600 transition-all" :style="'width:' + occupancyPercent + '%'"></div>
        </div>
        <p class="mt-2 text-xs text-gray-400"><span x-text="occupiedCount"></span> of <span x-text="rooms.length"></span> rooms currently occupied</p>
    </div>

    <div class="mb-5 flex gap-1 overflow-x-auto border-b border-gray-200">
        <button @click="activeTab = 'rooms'; statusFilter = 'all'" :class="activeTab === 'rooms' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold">Room board</button>
        <button @click="activeTab = 'reservations'; statusFilter = 'all'" :class="activeTab === 'reservations' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold">Reservations <span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-700" x-text="upcomingReservations.length"></span></button>
        <button @click="activeTab = 'housekeeping'; statusFilter = 'all'" :class="activeTab === 'housekeeping' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold">Housekeeping</button>
        <button @click="activeTab = 'finance'; statusFilter = 'all'" :class="activeTab === 'finance' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold">Income &amp; expenses</button>
    </div>

    <section x-show="activeTab === 'rooms'">
        <div class="mb-4 flex flex-wrap gap-2">
            <template x-for="filter in ['all', 'available', 'occupied', 'reserved', 'cleaning', 'maintenance']" :key="filter">
                <button @click="statusFilter = filter" :class="statusFilter === filter ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'" class="rounded-lg px-3 py-1.5 text-xs font-semibold capitalize" x-text="filter === 'all' ? 'All rooms' : statusLabel(filter)"></button>
            </template>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <template x-for="room in filteredRooms" :key="room.id">
                <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">@heroicon('o-key', 'h-6 w-6')</div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Room <span x-text="room.number"></span></h2>
                                <p class="text-xs text-gray-500"><span x-text="room.type"></span> · <span x-text="room.floor"></span></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold ring-1 ring-inset" :class="statusClasses(room.status)" x-text="statusLabel(room.status)"></span>
                            <button x-show="can.manage" @click="openRoomModal(room)" :aria-label="'Edit room ' + room.number" title="Edit room" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                @heroicon('o-pencil-square', 'h-4 w-4')
                            </button>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-gray-100 pt-3">
                        <template x-if="room.stay">
                            <div>
                                <p class="text-sm font-semibold text-gray-800" x-text="room.stay.guest"></p>
                                <p class="mt-0.5 text-xs text-gray-500"><span x-text="daysStayed(room)"></span> day(s) so far · <span x-text="room.stay.guests"></span> guest(s)</p>
                                <p class="mt-1 text-xs text-gray-500">Expected sign-out <span x-text="room.stay.expectedCheckoutAt.replace('T', ' ')"></span></p>
                                <p class="mt-2 text-xs font-semibold text-blue-700" x-text="'Balance: ' + formatMoney(stayTotal(room))"></p>
                            </div>
                        </template>
                        <template x-if="room.reservation">
                            <div>
                                <p class="text-sm font-semibold text-gray-800" x-text="room.reservation.guest"></p>
                                <p class="mt-0.5 text-xs text-gray-500">Arrival <span x-text="room.reservation.checkInDate"></span> · <span x-text="room.reservation.nights"></span> night(s)</p>
                            </div>
                        </template>
                        <template x-if="!room.stay && !room.reservation">
                            <div>
                                <p class="text-sm font-semibold text-gray-800" x-text="formatMoney(room.rate) + ' / night'"></p>
                                <p class="mt-0.5 text-xs text-gray-500"><span x-text="room.capacity"></span> guest capacity · <span x-text="room.amenities"></span></p>
                            </div>
                        </template>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button x-show="room.status === 'available' && can.checkIn" @click="openCheckIn(room)" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Check in</button>
                        <button x-show="room.status === 'available' && can.reservations" @click="openBooking(room)" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Reserve</button>
                        <button x-show="room.status === 'occupied'" @click="openStay(room)" class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100">View stay / folio</button>
                        <button x-show="room.status === 'reserved'" @click="activeTab = 'reservations'" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100">View reservation</button>
                        <button x-show="room.status === 'cleaning' && can.housekeeping" @click="markRoomReady(room)" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Mark room ready</button>
                        <span x-show="room.status === 'maintenance'" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-medium text-gray-500">Unavailable for guests</span>
                    </div>
                </article>
            </template>
        </div>
        <div x-show="rooms.length === 0" class="rounded-xl border border-dashed border-gray-300 py-12 text-center text-sm text-gray-500">
            No rooms have been added yet.
            <button x-show="can.manage" @click="openRoomModal()" class="ml-1 font-semibold text-blue-600 hover:text-blue-700">Add your first room</button>
        </div>
        <div x-show="rooms.length > 0 && filteredRooms.length === 0" class="rounded-xl border border-dashed border-gray-300 py-12 text-center text-sm text-gray-500">No rooms match this filter.</div>
    </section>

    <section x-show="activeTab === 'reservations'" x-cloak class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-bold text-gray-900">Upcoming reservations</h2>
                <p class="mt-0.5 text-xs text-gray-500">Prepare rooms and check guests in when they arrive.</p>
            </div>
            <button x-show="can.reservations" @click="openBooking()" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Add reservation</button>
        </div>
        <template x-if="upcomingReservations.length === 0">
            <p class="p-8 text-center text-sm text-gray-500">There are no upcoming reservations.</p>
        </template>
        <div class="divide-y divide-gray-100">
            <template x-for="reservation in upcomingReservations" :key="reservation.id">
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-gray-900" x-text="reservation.guest"></p>
                        <p class="mt-1 text-xs text-gray-500"><span x-text="'Room ' + ((roomForReservation(reservation) || {}).number || '—')"></span> · Arrival <span x-text="reservation.checkInDate"></span> · <span x-text="reservation.nights"></span> night(s) · <span x-text="reservation.guests"></span> guest(s)</p>
                        <p class="mt-1 text-xs text-gray-400" x-text="reservation.phone"></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-gray-500" x-text="'Deposit: ' + formatMoney(reservation.deposit)"></span>
                        <button x-show="can.checkIn" @click="checkInReservation(reservation)" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Check in guest</button>
                        <button x-show="can.reservations" @click="askCancelReservation(reservation)" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">Cancel</button>
                    </div>
                </div>
            </template>
        </div>
    </section>

    <section x-show="activeTab === 'housekeeping'" x-cloak>
        <div class="mb-4 rounded-xl border border-orange-100 bg-orange-50 p-4">
            <h2 class="text-sm font-bold text-orange-900">Room turnover</h2>
            <p class="mt-1 text-xs text-orange-800">After checkout, a room needs cleaning before it can be assigned to another guest. Mark it ready when housekeeping is finished.</p>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <template x-for="room in rooms.filter(function (item) { return item.status === 'cleaning' || item.status === 'maintenance'; })" :key="room.id">
                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div>
                        <p class="font-bold text-gray-900">Room <span x-text="room.number"></span></p>
                        <p class="text-xs text-gray-500"><span x-text="room.type"></span> · <span x-text="statusLabel(room.status)"></span></p>
                    </div>
                    <button x-show="room.status === 'cleaning' && can.housekeeping" @click="markRoomReady(room)" class="whitespace-nowrap rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Mark ready</button>
                    <span x-show="room.status === 'maintenance'" class="text-xs font-medium text-gray-500">Maintenance</span>
                </div>
            </template>
        </div>
        <div x-show="housekeepingCount === 0 && rooms.filter(function (room) { return room.status === 'maintenance'; }).length === 0" class="rounded-xl border border-dashed border-gray-300 py-12 text-center text-sm text-gray-500">All rooms are ready for guests.</div>
    </section>

    <section x-show="activeTab === 'finance'" x-cloak>
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-bold text-gray-900">Room income &amp; expenses</h2>
                <p class="mt-1 text-xs text-gray-500">Income is counted when a guest payment is recorded. Profit is income minus room expenses.</p>
            </div>
            <button x-show="can.finance" @click="openExpenseModal()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                @heroicon('o-plus', 'h-4 w-4')
                Add room expense
            </button>
        </div>
        <div class="mb-5 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="income-date-from" class="block text-xs font-semibold text-gray-600">From date</label>
                <input id="income-date-from" type="date" x-model="incomeDateFrom" :max="incomeDateTo || null" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm outline-none focus:border-blue-500 sm:max-w-xs">
            </div>
            <div class="flex-1">
                <label for="income-date-to" class="block text-xs font-semibold text-gray-600">To date</label>
                <input id="income-date-to" type="date" x-model="incomeDateTo" :min="incomeDateFrom || null" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm outline-none focus:border-blue-500 sm:max-w-xs">
            </div>
            <button type="button" @click="incomeDateFrom = ''; incomeDateTo = ''" :disabled="!incomeDateFrom && !incomeDateTo" class="h-10 rounded-lg border border-gray-200 px-4 text-sm font-semibold text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">Clear dates</button>
        </div>
        <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                <p class="text-xs font-semibold text-emerald-700">Guest payments received</p>
                <p class="mt-1 text-xl font-bold text-emerald-900" x-text="formatMoney(totalIncome)"></p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                <p class="text-xs font-semibold text-amber-700">Room expenses</p>
                <p class="mt-1 text-xl font-bold text-amber-900" x-text="formatMoney(totalExpenses)"></p>
            </div>
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <p class="text-xs font-semibold text-blue-700">Net room profit</p>
                <p class="mt-1 text-xl font-bold" :class="netProfit >= 0 ? 'text-blue-900' : 'text-red-700'" x-text="formatMoney(netProfit)"></p>
            </div>
        </div>

        <div class="mb-5 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-4 py-3">
                <h3 class="text-sm font-bold text-gray-900">Daily income and profit</h3>
                <p class="mt-0.5 text-xs text-gray-500">Daily income reflects payments actually received that day, not the room's outstanding balance.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[540px] text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3 text-right">Guest payments</th><th class="px-4 py-3 text-right">Expenses</th><th class="px-4 py-3 text-right">Net profit</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="day in dailyFinancials" :key="day.date">
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800" x-text="day.date"></td>
                                <td class="px-4 py-3 text-right text-emerald-700" x-text="formatMoney(day.income)"></td>
                                <td class="px-4 py-3 text-right text-amber-700" x-text="formatMoney(day.expenses)"></td>
                                <td class="px-4 py-3 text-right font-semibold" :class="day.profit >= 0 ? 'text-gray-900' : 'text-red-700'" x-text="formatMoney(day.profit)"></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot x-show="dailyFinancials.length === 0"><tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No payments or expenses have been recorded.</td></tr></tfoot>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-4 py-3">
                <h3 class="text-sm font-bold text-gray-900">Room expenses</h3>
                <p class="mt-0.5 text-xs text-gray-500">Supplies such as soap and utilities such as electricity are included in room profit.</p>
            </div>
            <div class="divide-y divide-gray-100">
                <template x-for="expense in filteredRoomExpenses.slice().sort(function (a, b) { return b.date.localeCompare(a.date); })" :key="expense.id">
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-800" x-text="expense.description"></p>
                            <p class="mt-0.5 text-xs text-gray-500"><span x-text="expense.category"></span> · <span x-text="expense.date"></span></p>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-2">
                            <span class="text-sm font-semibold text-gray-900" x-text="formatMoney(expense.amount)"></span>
                            <button x-show="can.finance" type="button" @click="askRemoveRoomExpense(expense)" :aria-label="'Delete ' + expense.description" title="Delete expense" class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                @heroicon('o-trash', 'h-4 w-4')
                            </button>
                        </div>
                    </div>
                </template>
                <div x-show="filteredRoomExpenses.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">No room expenses for the selected date range.</div>
            </div>
        </div>

        <div class="mt-5 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-4 py-3">
                <h3 class="text-sm font-bold text-gray-900">Recent completed stays</h3>
                <p class="mt-0.5 text-xs text-gray-500">Check-in and sign-out times with the final stay duration.</p>
            </div>
            <div class="divide-y divide-gray-100">
                <template x-for="stay in completedStays" :key="stay.id">
                    <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800"><span x-text="stay.guest"></span> · Room <span x-text="stay.room"></span></p>
                            <p class="mt-0.5 text-xs text-gray-500">In <span x-text="stay.checkInAt.replace('T', ' ')"></span> · Out <span x-text="stay.checkOutAt.replace('T', ' ')"></span></p>
                        </div>
                        <div class="flex gap-3 text-xs">
                            <span class="font-medium text-gray-600" x-text="stay.days + ' day(s)'"></span>
                            <span class="font-semibold text-gray-900" x-text="formatMoney(stay.total)"></span>
                        </div>
                    </div>
                </template>
                <div x-show="completedStays.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">Completed stays will appear here after checkout.</div>
            </div>
        </div>
    </section>

    <template x-teleport="body">
        <div x-show="showExpenseModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showExpenseModal = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div><h2 class="font-bold text-gray-900">Add room expense</h2><p class="text-xs text-gray-500">Record costs that reduce room profit.</p></div>
                    <button @click="showExpenseModal = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100">@heroicon('o-x-mark', 'h-5 w-5')</button>
                </div>
                <div x-show="actionError" x-cloak class="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"><span x-text="actionError"></span></div>
                <form @submit.prevent="addRoomExpense()" class="space-y-4 p-5">
                    <label class="block text-xs font-semibold text-gray-600">Description<input x-model="expenseForm.description" required placeholder="e.g. Room soap, electricity" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-gray-600">Category<select x-model="expenseForm.category" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm font-normal"><option>Supplies</option><option>Utilities</option><option>Repairs</option><option>Laundry</option><option>Other</option></select></label>
                        <label class="text-xs font-semibold text-gray-600">Amount<input type="number" min="1" x-model="expenseForm.amount" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-gray-600">Room (optional)
                            <select x-model="expenseForm.roomId" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm font-normal">
                                <option value="">All rooms</option>
                                <template x-for="room in rooms" :key="room.id">
                                    <option :value="room.id" x-text="'Room ' + room.number"></option>
                                </template>
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Expense date<input type="date" x-model="expenseForm.date" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                    </div>
                    <div class="flex gap-2 border-t border-gray-100 pt-4">
                        <button type="button" @click="showExpenseModal = false" class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600">Cancel</button>
                        <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Save expense</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="showCheckInModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showCheckInModal = false"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div><h2 class="font-bold text-gray-900">Check in guest</h2><p class="text-xs text-gray-500" x-text="selectedRoom ? 'Room ' + selectedRoom.number + ' · ' + selectedRoom.type : ''"></p></div>
                    <button @click="showCheckInModal = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100">@heroicon('o-x-mark', 'h-5 w-5')</button>
                </div>
                <div x-show="actionError" x-cloak class="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"><span x-text="actionError"></span></div>
                <form @submit.prevent="checkInGuest()" class="space-y-4 p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-gray-600 sm:col-span-2">Guest full name<input x-model="checkInForm.guest" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal outline-none focus:border-blue-500" placeholder="Enter guest name"></label>
                        <label class="text-xs font-semibold text-gray-600 sm:col-span-2">Phone number<input x-model="checkInForm.phone" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal outline-none focus:border-blue-500" placeholder="+255 ..."></label>
                        <label class="text-xs font-semibold text-gray-600">Number of guests<input type="number" min="1" x-model.number="checkInForm.guests" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                        <label class="text-xs font-semibold text-gray-600">Check-in date &amp; time<input type="datetime-local" x-model="checkInForm.checkInAt" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                        <label class="text-xs font-semibold text-gray-600 sm:col-span-2">Expected sign-out date &amp; time<input type="datetime-local" x-model="checkInForm.expectedCheckoutAt" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"><span class="mt-1 block font-normal text-gray-400">The actual sign-out time will be recorded at checkout.</span></label>
                        <label class="text-xs font-semibold text-gray-600">Rate per night<input type="number" min="0" x-model.number="checkInForm.rate" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                        <label class="text-xs font-semibold text-gray-600">Deposit received<input type="number" min="0" x-model.number="checkInForm.deposit" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                        <div>
                            <p class="text-xs font-semibold text-blue-900">Estimated room amount</p>
                            <p class="mt-0.5 text-xs text-blue-700"><span x-text="checkInNights()"></span> night(s) × rate per night</p>
                        </div>
                        <p class="text-base font-bold text-blue-900" x-text="formatMoney(checkInEstimate())"></p>
                    </div>
                    <div class="flex gap-2 border-t border-gray-100 pt-4">
                        <button type="button" @click="showCheckInModal = false" class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Complete check-in</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="showBookingModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showBookingModal = false"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div><h2 class="font-bold text-gray-900" x-text="selectedRoom && selectedRoom.status === 'reserved' ? 'Reservation details' : 'New reservation'"></h2><p class="text-xs text-gray-500">Record the guest and expected arrival.</p></div>
                    <button @click="showBookingModal = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100">@heroicon('o-x-mark', 'h-5 w-5')</button>
                </div>
                <div x-show="actionError" x-cloak class="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"><span x-text="actionError"></span></div>
                <form @submit.prevent="createReservation()" class="space-y-4 p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-gray-600 sm:col-span-2">Guest full name<input x-model="bookingForm.guest" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                        <label class="text-xs font-semibold text-gray-600 sm:col-span-2">Phone number<input x-model="bookingForm.phone" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                        <label class="text-xs font-semibold text-gray-600">Room<select x-model="bookingForm.roomId" @change="bookingForm.rate = (rooms.filter(function (room) { return String(room.id) === String(bookingForm.roomId); })[0] || {}).rate || 0" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm font-normal"><option value="">Select available room</option><template x-for="room in rooms.filter(function (item) { return item.status === 'available'; })" :key="room.id"><option :value="room.id" x-text="'Room ' + room.number + ' · ' + room.type"></option></template></select></label>
                        <label class="text-xs font-semibold text-gray-600">Arrival date<input type="date" x-model="bookingForm.checkInDate" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                        <label class="text-xs font-semibold text-gray-600">Number of guests<input type="number" min="1" x-model.number="bookingForm.guests" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                        <label class="text-xs font-semibold text-gray-600">Nights<input type="number" min="1" x-model.number="bookingForm.nights" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                        <label class="text-xs font-semibold text-gray-600">Rate per night<input type="number" min="0" x-model.number="bookingForm.rate" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                        <label class="text-xs font-semibold text-gray-600">Deposit<input type="number" min="0" x-model.number="bookingForm.deposit" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal"></label>
                    </div>
                    <div class="flex gap-2 border-t border-gray-100 pt-4">
                        <button type="button" @click="showBookingModal = false" class="flex-1 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600">Cancel</button>
                        <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Save reservation</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="showStayModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showStayModal = false"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div><h2 class="font-bold text-gray-900">Guest stay</h2><p class="text-xs text-gray-500" x-text="selectedRoom && selectedRoom.stay ? 'Room ' + selectedRoom.number + ' · ' + selectedRoom.stay.guest : ''"></p></div>
                    <button @click="showStayModal = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100">@heroicon('o-x-mark', 'h-5 w-5')</button>
                </div>
                <div x-show="actionError" x-cloak class="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"><span x-text="actionError"></span></div>
                <div x-show="selectedRoom && selectedRoom.stay" class="space-y-4 p-5">
                    <div class="grid grid-cols-2 gap-3 rounded-xl bg-gray-50 p-4 text-xs">
                        <p class="text-gray-500">Phone <span class="mt-1 block font-semibold text-gray-800" x-text="selectedRoom && selectedRoom.stay ? selectedRoom.stay.phone : ''"></span></p>
                        <p class="text-gray-500">Checked in <span class="mt-1 block font-semibold text-gray-800" x-text="selectedRoom && selectedRoom.stay ? selectedRoom.stay.checkInAt.replace('T', ' ') : ''"></span></p>
                        <p class="text-gray-500">Time stayed <span class="mt-1 block font-semibold text-gray-800" x-text="selectedRoom ? daysStayed(selectedRoom) + ' day(s)' : ''"></span></p>
                        <p class="text-gray-500">Expected sign-out <span class="mt-1 block font-semibold text-gray-800" x-text="selectedRoom && selectedRoom.stay ? selectedRoom.stay.expectedCheckoutAt.replace('T', ' ') : ''"></span></p>
                        <p class="text-gray-500">Room charges so far <span class="mt-1 block font-semibold text-gray-800" x-text="selectedRoom ? formatMoney(stayCharges(selectedRoom)) : ''"></span></p>
                    </div>
                    <div>
                        <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Folio / extra charges</h3>
                        <template x-if="selectedRoom && selectedRoom.stay.charges.length === 0"><p class="text-xs text-gray-400">No extra charges recorded.</p></template>
                        <div class="divide-y divide-gray-100 rounded-lg border border-gray-100">
                            <template x-for="charge in selectedRoom ? selectedRoom.stay.charges : []" :key="charge.id">
                                <div class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                    <span class="min-w-0 flex-1 truncate text-gray-700" x-text="charge.description"></span>
                                    <span class="font-semibold text-gray-900" x-text="formatMoney(charge.amount)"></span>
                                    <button x-show="can.checkIn" type="button" @click="askRemoveStayCharge(charge)" :aria-label="'Delete charge ' + charge.description" title="Delete charge" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">@heroicon('o-trash', 'h-4 w-4')</button>
                                </div>
                            </template>
                        </div>
                        <form x-show="can.checkIn" @submit.prevent="addStayCharge()" class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <input x-model="chargeForm.description" required placeholder="e.g. Laundry, minibar" class="h-10 min-w-0 flex-1 rounded-lg border border-gray-200 px-3 text-sm">
                            <input type="number" min="1" x-model="chargeForm.amount" required placeholder="Amount" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm sm:w-28">
                            <button type="submit" class="h-10 rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50">Add charge</button>
                        </form>
                    </div>
                    <div>
                        <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Guest payments</h3>
                        <div class="divide-y divide-gray-100 rounded-lg border border-gray-100">
                            <template x-for="payment in selectedRoom ? selectedRoom.stay.payments : []" :key="payment.id">
                                <div class="flex justify-between gap-2 px-3 py-2 text-xs">
                                    <span class="text-gray-500"><span x-text="payment.date"></span> · <span x-text="payment.method"></span></span>
                                    <span class="font-semibold text-emerald-700" x-text="formatMoney(payment.amount)"></span>
                                </div>
                            </template>
                            <div x-show="selectedRoom && selectedRoom.stay.payments.length === 0" class="px-3 py-2 text-xs text-gray-400">No payments recorded yet.</div>
                        </div>
                        <form x-show="can.checkIn || can.checkOut" @submit.prevent="addStayPayment()" class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <input type="number" min="1" x-model="paymentForm.amount" required placeholder="Payment amount" class="h-10 min-w-0 flex-1 rounded-lg border border-gray-200 px-3 text-sm">
                            <select x-model="paymentForm.method" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm"><option>Cash</option><option>Card</option><option>Mobile money</option><option>Bank transfer</option></select>
                            <button type="submit" class="h-10 rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50">Record payment</button>
                        </form>
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                        <div><p class="text-xs text-gray-500">Balance due</p><p class="text-lg font-bold text-gray-900" x-text="selectedRoom ? formatMoney(stayTotal(selectedRoom)) : ''"></p></div>
                        <button x-show="can.checkOut" @click="openCheckout(selectedRoom)" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Check out guest</button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="showCheckoutModal" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showCheckoutModal = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <div class="mb-4 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">@heroicon('o-receipt-percent', 'h-6 w-6')</div>
                    <h2 class="text-lg font-bold text-gray-900">Complete guest checkout</h2>
                    <p class="mt-1 text-sm text-gray-500" x-text="selectedRoom && selectedRoom.stay ? selectedRoom.stay.guest + ' · Room ' + selectedRoom.number : ''"></p>
                </div>
                <div x-show="actionError" x-cloak class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"><span x-text="actionError"></span></div>
                <div class="mb-5 rounded-xl bg-gray-50 p-4">
                    <label class="block text-xs font-semibold text-gray-600">Actual sign-out date &amp; time
                        <input type="datetime-local" x-model="checkoutForm.checkoutAt" @input="checkoutForm.amountReceived = checkoutBalance()" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm font-normal">
                    </label>
                    <div class="mt-3 flex justify-between text-sm"><span class="text-gray-500">Days stayed</span><span class="font-semibold text-gray-900" x-text="selectedRoom ? daysStayed(selectedRoom, checkoutForm.checkoutAt) + ' day(s)' : ''"></span></div>
                    <div class="mt-2 flex justify-between text-sm"><span class="text-gray-500">Total stay charges</span><span class="font-semibold text-gray-900" x-text="selectedRoom ? formatMoney(stayCharges(selectedRoom, checkoutForm.checkoutAt)) : ''"></span></div>
                    <div class="mt-2 flex justify-between text-sm"><span class="text-gray-500">Balance due</span><span class="font-bold text-gray-900" x-text="formatMoney(checkoutBalance())"></span></div>
                    <label class="mt-4 block text-xs font-semibold text-gray-600">Payment method
                        <select x-model="checkoutForm.paymentMethod" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm font-normal">
                            <option>Cash</option><option>Card</option><option>Mobile money</option><option>Bank transfer</option>
                        </select>
                    </label>
                    <label class="mt-3 block text-xs font-semibold text-gray-600">Amount received
                        <input type="number" min="0" x-model.number="checkoutForm.amountReceived" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm font-normal">
                    </label>
                    <p x-show="checkoutChange() > 0" class="mt-2 text-xs font-semibold text-blue-700" x-text="'Change to return: ' + formatMoney(checkoutChange())"></p>
                    <p class="mt-2 text-[11px] leading-relaxed text-gray-500">Checkout records the sign-out time and payment. The room then goes to housekeeping.</p>
                </div>
                <div class="flex gap-2">
                    <button @click="showCheckoutModal = false" class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Back to stay</button>
                    <button @click="checkOutGuest()" :disabled="!checkoutForm.checkoutAt || Number(checkoutForm.amountReceived) < checkoutBalance()" class="flex-1 rounded-lg bg-emerald-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Confirm checkout</button>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="showRoomModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showRoomModal = false"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                        <h2 class="font-bold text-gray-900" x-text="editingRoomId ? 'Edit room' : 'Add a new room'"></h2>
                        <p class="text-xs text-gray-500">Room details are saved to the database.</p>
                    </div>
                    <button @click="showRoomModal = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100">@heroicon('o-x-mark', 'h-5 w-5')</button>
                </div>
                <div x-show="actionError" x-cloak class="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"><span x-text="actionError"></span></div>
                <form @submit.prevent="saveRoom()" class="space-y-4 p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-gray-600">Room number
                            <input x-model="roomForm.number" required maxlength="24" placeholder="e.g. 101" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal outline-none focus:border-blue-500">
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Room type
                            <select x-model="roomForm.type" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm font-normal outline-none focus:border-blue-500">
                                @foreach ($roomTypes as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Floor
                            <input x-model="roomForm.floor" maxlength="60" placeholder="e.g. First floor" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal outline-none focus:border-blue-500">
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Rate per night
                            <input type="number" min="0" step="0.01" x-model.number="roomForm.rate" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal outline-none focus:border-blue-500">
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Guest capacity
                            <input type="number" min="1" max="100" x-model.number="roomForm.capacity" required class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal outline-none focus:border-blue-500">
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Room status
                            <select x-model="roomForm.status" :disabled="editingRoomId && (roomForm.status === 'occupied' || roomForm.status === 'reserved')" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm font-normal outline-none focus:border-blue-500 disabled:bg-gray-100 disabled:text-gray-500">
                                <option value="available">Available</option>
                                <option value="cleaning">Needs cleaning</option>
                                <option value="maintenance">Maintenance</option>
                                <template x-if="roomForm.status === 'occupied'">
                                    <option value="occupied">Occupied</option>
                                </template>
                                <template x-if="roomForm.status === 'reserved'">
                                    <option value="reserved">Reserved</option>
                                </template>
                            </select>
                            <span class="mt-1 block font-normal text-gray-400">Occupied and reserved status is controlled by guest stays and reservations.</span>
                        </label>
                        <label class="text-xs font-semibold text-gray-600 sm:col-span-2">Amenities
                            <input x-model="roomForm.amenities" maxlength="500" placeholder="e.g. Queen bed · Wi-Fi · Air conditioning" class="mt-1.5 h-10 w-full rounded-lg border border-gray-200 px-3 text-sm font-normal outline-none focus:border-blue-500">
                        </label>
                        <label class="text-xs font-semibold text-gray-600 sm:col-span-2">Internal notes
                            <textarea x-model="roomForm.notes" rows="2" maxlength="2000" placeholder="Optional notes for staff" class="mt-1.5 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-normal outline-none focus:border-blue-500"></textarea>
                        </label>
                    </div>
                    <div class="flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <button type="button" x-show="editingRoomId" @click="askDeleteRoom(rooms.find(function (room) { return room.id === editingRoomId; }))" :disabled="saving" class="rounded-lg px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50">Delete room</button>
                        <div class="flex gap-2 sm:ml-auto">
                            <button type="button" @click="showRoomModal = false" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                            <button type="submit" :disabled="saving" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                                <span x-show="saving">Saving…</span>
                                <span x-show="!saving" x-text="editingRoomId ? 'Save changes' : 'Add room'"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="showDeleteRoomModal" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteRoomModal = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <div class="mb-4 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">@heroicon('o-trash', 'h-6 w-6')</div>
                    <h2 class="text-lg font-bold text-gray-900">Delete room</h2>
                    <p class="mt-1 text-sm text-gray-500">Delete Room <span class="font-semibold" x-text="roomToDelete ? roomToDelete.number : ''"></span>?</p>
                </div>
                <p class="mb-5 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">This cannot be undone. Rooms with stay, reservation or finance history cannot be deleted; mark those rooms as maintenance instead.</p>
                <div x-show="actionError" x-cloak class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"><span x-text="actionError"></span></div>
                <div class="flex gap-2">
                    <button @click="showDeleteRoomModal = false" class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button @click="confirmDeleteRoom()" :disabled="saving" class="flex-1 rounded-lg bg-red-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">Delete room</button>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="showCancelReservationModal" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showCancelReservationModal = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <div class="mb-4 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">@heroicon('o-calendar-days', 'h-6 w-6')</div>
                    <h2 class="text-lg font-bold text-gray-900">Cancel reservation</h2>
                    <p class="mt-1 text-sm text-gray-500">Cancel the reservation for <span class="font-semibold" x-text="reservationToCancel ? reservationToCancel.guest : ''"></span>?</p>
                </div>
                <p class="mb-5 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">The room will be released back to the available list. Any deposit already recorded stays in the room income history.</p>
                <div x-show="actionError" x-cloak class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"><span x-text="actionError"></span></div>
                <div class="flex gap-2">
                    <button @click="showCancelReservationModal = false" class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Keep reservation</button>
                    <button @click="confirmCancelReservation()" :disabled="saving" class="flex-1 rounded-lg bg-amber-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">Cancel reservation</button>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="showConfirmModal" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showConfirmModal = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <div class="mb-4 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">@heroicon('o-exclamation-triangle', 'h-6 w-6')</div>
                    <h2 class="text-lg font-bold text-gray-900" x-text="confirmTitle"></h2>
                    <p class="mt-1 text-sm text-gray-500" x-text="confirmMessage"></p>
                </div>
                <div x-show="actionError" x-cloak class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"><span x-text="actionError"></span></div>
                <div class="flex gap-2">
                    <button @click="showConfirmModal = false" class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button @click="runConfirm()" :disabled="saving" x-text="confirmLabel" class="flex-1 rounded-lg bg-red-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"></button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
