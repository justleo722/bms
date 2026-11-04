@extends('layouts.master', ['activeNav' => $activeNav ?? 'tables'])

@section('title', 'Table Management')

@push('head')
<script>
window.tablesData = function (initialQuery) {
    return {
        viewMode: 'floor-plan',
        selectedStatus: 'all',
        searchQuery: initialQuery || '',
        selectedTable: null,
        showDetailModal: false,
        showNewTableModal: false,

        statusFilters: [
            { id: 'all', label: 'All', color: 'gray' },
            { id: 'available', label: 'Available', color: 'green' },
            { id: 'occupied', label: 'Occupied', color: 'red' },
            { id: 'reserved', label: 'Reserved', color: 'orange' },
            { id: 'cleaning', label: 'Cleaning', color: 'blue' },
            { id: 'merged', label: 'Merged', color: 'purple' },
            { id: 'blocked', label: 'Blocked', color: 'gray' },
        ],

        tables: window.BmsMock.tables,

        get filteredTables() {
            var q = this.searchQuery.toLowerCase().trim();
            return this.tables.filter(function (t) {
                if (this.selectedStatus !== 'all' && t.status !== this.selectedStatus) return false;
                if (!q) return true;
                var haystack = [t.name, t.waiter, t.party, t.status, t.mergedWith]
                    .map(function (v) { return String(v || '').toLowerCase(); })
                    .join(' ');
                return haystack.includes(q);
            }.bind(this));
        },

        get statusCounts() {
            var counts = { all: this.tables.length };
            this.tables.forEach(function (t) { counts[t.status] = (counts[t.status] || 0) + 1; });
            return counts;
        },

        getStatusColor(status) {
            return {
                available: 'border-emerald-400 bg-emerald-50 text-emerald-700',
                occupied: 'border-red-400 bg-red-50 text-red-700',
                reserved: 'border-amber-400 bg-amber-50 text-amber-700',
                cleaning: 'border-blue-400 bg-blue-50 text-blue-700',
                merged: 'border-purple-400 bg-purple-50 text-purple-700',
                blocked: 'border-gray-400 bg-gray-100 text-gray-500',
            }[status] || 'border-gray-200 bg-white text-gray-700';
        },

        getStatusBadge(status) {
            return {
                available: 'bg-emerald-500',
                occupied: 'bg-red-500',
                reserved: 'bg-amber-500',
                cleaning: 'bg-blue-500',
                merged: 'bg-purple-500',
                blocked: 'bg-gray-400',
            }[status] || 'bg-gray-400';
        },

        getStatusLabel(status) {
            return status.charAt(0).toUpperCase() + status.slice(1);
        },

        setView(mode) {
            this.viewMode = mode;
        },

        openDetail(table) {
            this.selectedTable = table;
            this.showDetailModal = true;
        },

        closeDetail() {
            this.showDetailModal = false;
            this.selectedTable = null;
        },

        changeStatus(table, status) {
            table.status = status;
        },

        openNewTable() {
            this.showNewTableModal = true;
        },

        formatMoney(v) {
            return (window.BmsMoney ? window.BmsMoney.symbol : '$') + ' ' + (v || 0).toFixed(2);
        },
    };
};
</script>
<style>
.floor-table {
    transition: all 0.2s ease;
}
.floor-table:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    z-index: 10;
}
.floor-plan-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
.floor-plan-scroll::-webkit-scrollbar-track { background: transparent; }
.floor-plan-scroll::-webkit-scrollbar-thumb { background: #d4d4d4; border-radius: 8px; }

@keyframes pulse-soft {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.status-pulse { animation: pulse-soft 2s ease-in-out infinite; }
</style>
@endpush

@section('content')
<div x-data="tablesData(@js(is_string(request('q')) ? request('q') : ''))" class="pb-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Table Management</h1>
            <p class="mt-1 text-sm text-gray-500">
                <span x-text="tables.length"></span> tables &middot;
                <span x-text="statusCounts.available || 0"></span> available &middot;
                <span x-text="statusCounts.occupied || 0"></span> occupied &middot;
                <span x-text="statusCounts.reserved || 0"></span> reserved &middot;
                <span x-text="statusCounts.cleaning || 0"></span> cleaning
            </p>
        </div>
        <div class="flex items-center gap-2 mt-4 sm:mt-0">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model.debounce.300ms="searchQuery" placeholder="Search tables..." class="w-48 h-10 pl-10 pr-4 text-sm bg-white border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all placeholder:text-gray-400">
            </div>
            <div class="flex items-center bg-white rounded-xl border border-gray-200 p-0.5 shadow-sm">
                <button @click="setView('floor-plan')"
                        :class="viewMode === 'floor-plan' ? 'bg-blue-600 text-white shadow-sm shadow-blue-200' : 'text-gray-500 hover:text-gray-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-150 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                    Floor
                </button>
                <button @click="setView('grid')"
                        :class="viewMode === 'grid' ? 'bg-blue-600 text-white shadow-sm shadow-blue-200' : 'text-gray-500 hover:text-gray-700'"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-150 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Grid
                </button>
            </div>
        </div>
    </div>

    {{-- Status Filter Chips --}}
    <div class="flex items-center gap-2 mb-5 overflow-x-auto no-scrollbar">
        <template x-for="f in statusFilters" :key="f.id">
            <button @click="selectedStatus = f.id"
                    :class="selectedStatus === f.id ? 'ring-2 ring-offset-2' : ''"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-medium transition-all duration-150 flex-shrink-0"
                    :class="{
                        'bg-gray-900 text-white ring-gray-900': selectedStatus === f.id && f.id === 'all',
                        'bg-gray-100 text-gray-600 hover:bg-gray-200': selectedStatus !== f.id && f.id === 'all',
                        'bg-emerald-50 text-emerald-700 ring-emerald-400': selectedStatus === f.id && f.id === 'available',
                        'bg-gray-50 text-gray-600 hover:bg-emerald-50': selectedStatus !== f.id && f.id === 'available',
                        'bg-red-50 text-red-700 ring-red-400': selectedStatus === f.id && f.id === 'occupied',
                        'bg-gray-50 text-gray-600 hover:bg-red-50': selectedStatus !== f.id && f.id === 'occupied',
                        'bg-amber-50 text-amber-700 ring-amber-400': selectedStatus === f.id && f.id === 'reserved',
                        'bg-gray-50 text-gray-600 hover:bg-amber-50': selectedStatus !== f.id && f.id === 'reserved',
                        'bg-blue-50 text-blue-700 ring-blue-400': selectedStatus === f.id && f.id === 'cleaning',
                        'bg-gray-50 text-gray-600 hover:bg-blue-50': selectedStatus !== f.id && f.id === 'cleaning',
                        'bg-purple-50 text-purple-700 ring-purple-400': selectedStatus === f.id && f.id === 'merged',
                        'bg-gray-50 text-gray-600 hover:bg-purple-50': selectedStatus !== f.id && f.id === 'merged',
                        'bg-gray-100 text-gray-500 ring-gray-400': selectedStatus === f.id && f.id === 'blocked',
                        'bg-gray-50 text-gray-600 hover:bg-gray-100': selectedStatus !== f.id && f.id === 'blocked',
                    }">
                <span class="w-2 h-2 rounded-full"
                      :class="{
                          'bg-gray-500': f.id === 'all',
                          'bg-emerald-500': f.id === 'available',
                          'bg-red-500': f.id === 'occupied',
                          'bg-amber-500': f.id === 'reserved',
                          'bg-blue-500': f.id === 'cleaning',
                          'bg-purple-500': f.id === 'merged',
                          'bg-gray-400': f.id === 'blocked',
                      }"></span>
                <span x-text="f.label"></span>
                <span class="text-xs opacity-60" x-text="'(' + (statusCounts[f.id] || 0) + ')'"></span>
            </button>
        </template>
    </div>

    {{-- ===== FLOOR PLAN VIEW ===== --}}
    <div x-show="viewMode === 'floor-plan'" x-cloak class="mb-6">
        <div class="relative bg-gradient-to-br from-amber-50/80 via-orange-50/60 to-amber-50/80 rounded-2xl border-2 border-amber-200/40 shadow-lg overflow-hidden floor-plan-scroll"
             style="height: 68vh; min-height: 480px;">

            {{-- Wall top --}}
            <div class="absolute top-0 left-0 right-0 h-1 bg-amber-300/40 z-10"></div>
            <div class="absolute top-1 left-0 right-0 h-6 bg-gradient-to-b from-amber-100/60 to-transparent z-0"></div>
            {{-- Wall left --}}
            <div class="absolute top-0 left-0 bottom-0 w-1 bg-amber-300/40 z-10"></div>
            {{-- Wall right --}}
            <div class="absolute top-0 right-0 bottom-0 w-1 bg-amber-300/40 z-10"></div>
            {{-- Wall bottom --}}
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber-300/40 z-10"></div>

            {{-- Windows --}}
            <div class="absolute top-2 left-[5%] right-[5%] h-2 flex items-center justify-center z-10">
                <div class="flex gap-6 w-full justify-center">
                    <template x-for="i in 8" :key="i">
                        <div class="w-12 h-1.5 bg-amber-200/50 rounded-full"></div>
                    </template>
                </div>
            </div>

            {{-- Section labels --}}
            <div class="absolute top-[8.5%] left-3 text-[10px] font-semibold text-amber-400/60 uppercase tracking-[0.2em] pointer-events-none z-10">Window</div>
            <div class="absolute top-[25.5%] left-3 text-[10px] font-semibold text-amber-400/60 uppercase tracking-[0.2em] pointer-events-none z-10">Dining</div>
            <div class="absolute top-[49.5%] left-3 text-[10px] font-semibold text-amber-400/60 uppercase tracking-[0.2em] pointer-events-none z-10">VIP</div>
            <div class="absolute top-[75.5%] left-3 text-[10px] font-semibold text-amber-400/60 uppercase tracking-[0.2em] pointer-events-none z-10">Main</div>
            <div class="absolute bottom-3 right-4 text-[10px] font-semibold text-amber-400/40 uppercase tracking-[0.2em] pointer-events-none z-10">Kitchen →</div>

            {{-- Divider lines --}}
            <div class="absolute border-t border-dashed border-amber-200/30 left-3 right-3" style="top: 21.5%;"></div>
            <div class="absolute border-t border-dashed border-amber-200/30 left-3 right-3" style="top: 45.5%;"></div>
            <div class="absolute border-t border-dashed border-amber-200/30 left-3 right-3" style="top: 71.5%;"></div>

            {{-- Kitchen area --}}
            <div class="absolute bottom-2 right-2 w-20 h-8 rounded-lg bg-amber-100/30 border border-dashed border-amber-300/30 flex items-center justify-center z-10">
                <span class="text-[9px] text-amber-500/60 font-medium">KITCHEN</span>
            </div>

            {{-- Tables on floor --}}
            <template x-for="table in filteredTables" :key="table.id">
                <div @click="openDetail(table)"
                     class="floor-table absolute cursor-pointer"
                     :style="`top: ${table.y}%; left: ${table.x}%; width: ${table.w}%; height: ${table.h}%;`">
                    {{-- Bar counter special styling --}}
                    <template x-if="table.shape === 'bar'">
                        <div class="h-full rounded-xl bg-gradient-to-b from-amber-100/90 to-amber-200/80 border-2 border-amber-300/60 shadow-md flex flex-col items-center justify-center text-center p-2 relative overflow-hidden">
                            <div class="absolute inset-0 bg-[repeating-linear-gradient(90deg,transparent,transparent_20px,rgba(251,191,36,0.1)_20px,rgba(251,191,36,0.1)_21px)]"></div>
                            <div class="relative z-10">
                                <span class="text-base font-bold text-amber-800">🍸 Bar Counter</span>
                                <div class="flex items-center justify-center gap-3 mt-0.5">
                                    <span class="text-[10px] text-amber-700/70 font-medium">✦ Capacity: <span x-text="table.capacity"></span></span>
                                    <span class="text-[10px] text-amber-700/70 font-medium">✦ Bartender: <span x-text="table.waiter"></span></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- VIP special styling --}}
                    <template x-if="table.shape === 'vip'">
                        <div class="h-full rounded-xl bg-gradient-to-br from-purple-50/95 to-purple-100/90 border-2 border-purple-300/60 shadow-md flex flex-col items-center justify-center text-center p-2 relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-12 h-12 bg-gradient-to-br from-purple-300/30 to-transparent rounded-bl-full"></div>
                            <div class="relative z-10 flex flex-col items-center justify-center h-full">
                                <span class="text-xs font-bold text-purple-700 uppercase tracking-wider" x-text="table.name"></span>
                                <div class="flex items-center gap-1 text-[10px] text-purple-600/70 mt-0.5">
                                    <span>👥 <span x-text="table.capacity"></span></span>
                                    <span>·</span>
                                    <span>🧑‍🍳 <span x-text="table.waiter"></span></span>
                                </div>
                                <template x-if="table.bill > 0">
                                    <span class="text-xs font-bold text-purple-700 mt-1" x-text="formatMoney(table.bill)"></span>
                                </template>
                                <template x-if="table.party">
                                    <span class="text-[9px] text-purple-600/60 mt-0.5 truncate max-w-full" x-text="table.party"></span>
                                </template>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-semibold mt-1"
                                      :class="{
                                          'bg-emerald-200/60 text-emerald-800': table.status === 'available',
                                          'bg-red-200/60 text-red-800': table.status === 'occupied',
                                          'bg-amber-200/60 text-amber-800': table.status === 'reserved',
                                          'bg-blue-200/60 text-blue-800': table.status === 'cleaning',
                                          'bg-purple-200/60 text-purple-800': table.status === 'merged',
                                          'bg-gray-200/60 text-gray-600': table.status === 'blocked',
                                      }">
                                    <span class="w-1.5 h-1.5 rounded-full status-pulse"
                                          :class="{ 'bg-emerald-500': table.status === 'available', 'bg-red-500': table.status === 'occupied', 'bg-amber-500': table.status === 'reserved', 'bg-blue-500': table.status === 'cleaning', 'bg-purple-500': table.status === 'merged', 'bg-gray-400': table.status === 'blocked' }"></span>
                                    <span x-text="getStatusLabel(table.status)"></span>
                                </span>
                            </div>
                        </div>
                    </template>

                    {{-- Regular tables --}}
                    <template x-if="table.shape === 'rect'">
                        <div class="h-full rounded-xl border-2 shadow-sm flex flex-col items-center justify-center text-center p-1.5 transition-all duration-200 overflow-hidden"
                             :class="getStatusColor(table.status)">
                            <span class="font-bold text-sm leading-tight" :class="{ 'text-gray-800': true }" x-text="table.name"></span>
                            <div class="flex items-center gap-1 text-[9px] opacity-75 mt-0.5">
                                <span>👥 <span x-text="table.capacity"></span></span>
                                <span>·</span>
                                <span>🧑‍🍳 <span x-text="table.waiter"></span></span>
                            </div>
                            <template x-if="table.bill > 0">
                                <span class="text-xs font-bold mt-0.5" :class="{ 'text-red-600': table.status === 'occupied' }" x-text="formatMoney(table.bill)"></span>
                            </template>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[8px] font-semibold mt-0.5 uppercase"
                                  :class="{
                                      'bg-emerald-200/60 text-emerald-800': table.status === 'available',
                                      'bg-red-200/60 text-red-800': table.status === 'occupied',
                                      'bg-amber-200/60 text-amber-800': table.status === 'reserved',
                                      'bg-blue-200/60 text-blue-800': table.status === 'cleaning',
                                      'bg-purple-200/60 text-purple-800': table.status === 'merged',
                                      'bg-gray-200/60 text-gray-600': table.status === 'blocked',
                                  }">
                                <span class="w-1 h-1 rounded-full status-pulse"
                                      :class="{ 'bg-emerald-500': table.status === 'available', 'bg-red-500': table.status === 'occupied', 'bg-amber-500': table.status === 'reserved', 'bg-blue-500': table.status === 'cleaning', 'bg-purple-500': table.status === 'merged', 'bg-gray-400': table.status === 'blocked' }"></span>
                                <span x-text="getStatusLabel(table.status)"></span>
                            </span>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Empty state --}}
            <template x-if="filteredTables.length === 0">
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-3xl mb-2">🔍</span>
                    <p class="text-sm font-medium text-gray-400">No tables match your filter</p>
                </div>
            </template>
        </div>

        {{-- Legend --}}
        <div class="flex items-center justify-center gap-6 mt-4 px-4 py-3 bg-white rounded-xl border border-gray-200 shadow-sm flex-wrap">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Legend</span>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <span class="text-xs text-gray-600">Available</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                <span class="text-xs text-gray-600">Occupied</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                <span class="text-xs text-gray-600">Reserved</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                <span class="text-xs text-gray-600">Cleaning</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                <span class="text-xs text-gray-600">Merged</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
                <span class="text-xs text-gray-600">Blocked</span>
            </div>
        </div>
    </div>

    {{-- ===== GRID VIEW ===== --}}
    <div x-show="viewMode === 'grid'" x-cloak class="mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <template x-for="table in filteredTables" :key="table.id">
                <div @click="openDetail(table)"
                     class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
                    <div class="h-2"
                         :class="{
                             'bg-emerald-500': table.status === 'available',
                             'bg-red-500': table.status === 'occupied',
                             'bg-amber-500': table.status === 'reserved',
                             'bg-blue-500': table.status === 'cleaning',
                             'bg-purple-500': table.status === 'merged',
                             'bg-gray-400': table.status === 'blocked',
                         }"></div>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-base font-bold text-gray-900" x-text="table.name"></h3>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold"
                                  :class="{
                                      'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20': table.status === 'available',
                                      'bg-red-50 text-red-700 ring-1 ring-red-600/20': table.status === 'occupied',
                                      'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20': table.status === 'reserved',
                                      'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20': table.status === 'cleaning',
                                      'bg-purple-50 text-purple-700 ring-1 ring-purple-600/20': table.status === 'merged',
                                      'bg-gray-100 text-gray-500 ring-1 ring-gray-400/20': table.status === 'blocked',
                                  }">
                                <span class="w-1.5 h-1.5 rounded-full status-pulse"
                                      :class="{
                                          'bg-emerald-500': table.status === 'available',
                                          'bg-red-500': table.status === 'occupied',
                                          'bg-amber-500': table.status === 'reserved',
                                          'bg-blue-500': table.status === 'cleaning',
                                          'bg-purple-500': table.status === 'merged',
                                          'bg-gray-400': table.status === 'blocked',
                                      }"></span>
                                <span x-text="getStatusLabel(table.status)"></span>
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 mb-3">
                            <div class="flex items-center gap-1.5">
                                <span>👥</span>
                                <span>Capacity: <strong class="text-gray-700" x-text="table.capacity"></strong></span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span>🧑‍🍳</span>
                                <span class="truncate">Server: <strong class="text-gray-700" x-text="table.waiter"></strong></span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                            <template x-if="table.party">
                                <span class="text-xs text-gray-500 truncate">Guest: <span class="font-medium text-gray-700" x-text="table.party"></span></span>
                            </template>
                            <template x-if="!table.party">
                                <span class="text-xs text-gray-400">No guest assigned</span>
                            </template>
                            <span class="text-sm font-bold text-gray-900" x-text="formatMoney(table.bill)"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <template x-if="filteredTables.length === 0">
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <span class="text-4xl mb-3">🔍</span>
                <p class="text-sm font-medium text-gray-500">No tables match your filter</p>
                <p class="text-xs text-gray-400 mt-1">Try a different status or search term</p>
            </div>
        </template>
    </div>

    {{-- ===== FLOATING ACTION BUTTON ===== --}}
    <button @click="openNewTable"
            class="fixed bottom-[84px] right-6 z-20 w-14 h-14 bg-gradient-to-br from-blue-600 to-blue-700 text-white rounded-full shadow-lg shadow-blue-200 hover:shadow-xl hover:scale-105 active:scale-95 transition-all duration-200 flex items-center justify-center">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
    </button>

    {{-- ===== TABLE DETAIL MODAL ===== --}}
    <template x-teleport="body">
        <div x-show="showDetailModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="closeDetail"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 z-10">
                <template x-if="selectedTable">
                    <div>
                        {{-- Header --}}
                        <div class="flex items-center justify-between mb-5">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl font-bold text-white"
                                     :class="{
                                         'bg-emerald-500': selectedTable.status === 'available',
                                         'bg-red-500': selectedTable.status === 'occupied',
                                         'bg-amber-500': selectedTable.status === 'reserved',
                                         'bg-blue-500': selectedTable.status === 'cleaning',
                                         'bg-purple-500': selectedTable.status === 'merged',
                                         'bg-gray-400': selectedTable.status === 'blocked',
                                     }">
                                    <span x-text="selectedTable.name.charAt(0) + (selectedTable.name.length > 1 && isNaN(selectedTable.name.charAt(1)) ? '' : '')"></span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900" x-text="selectedTable.name"></h3>
                                    <span class="text-sm font-medium"
                                          :class="{
                                              'text-emerald-600': selectedTable.status === 'available',
                                              'text-red-600': selectedTable.status === 'occupied',
                                              'text-amber-600': selectedTable.status === 'reserved',
                                              'text-blue-600': selectedTable.status === 'cleaning',
                                              'text-purple-600': selectedTable.status === 'merged',
                                              'text-gray-500': selectedTable.status === 'blocked',
                                          }">
                                        <span x-text="getStatusLabel(selectedTable.status)"></span>
                                    </span>
                                </div>
                            </div>
                            <button @click="closeDetail" class="p-1 text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Details --}}
                        <div class="grid grid-cols-2 gap-3 mb-5">
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider">Capacity</p>
                                <p class="text-lg font-bold text-gray-900 mt-0.5">👥 <span x-text="selectedTable.capacity"></span></p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider">Waiter</p>
                                <p class="text-lg font-bold text-gray-900 mt-0.5">🧑‍🍳 <span x-text="selectedTable.waiter"></span></p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider">Current Bill</p>
                                <p class="text-lg font-bold text-gray-900 mt-0.5" x-text="formatMoney(selectedTable.bill)"></p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider">Guest</p>
                                <p class="text-lg font-bold text-gray-900 mt-0.5 truncate" x-text="selectedTable.party || '—'"></p>
                            </div>
                        </div>

                        {{-- Change Status --}}
                            <div class="mb-5">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Change Status</p>
                            <div class="grid grid-cols-3 gap-2">
                                <button @click="changeStatus(selectedTable, 'available')"
                                        :class="selectedTable.status === 'available' ? 'ring-2 ring-emerald-400 bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-white text-gray-600 border-gray-200 hover:bg-emerald-50 hover:border-emerald-200'"
                                        class="py-2 rounded-lg border text-xs font-medium transition-all duration-150">🟢 Available</button>
                                <button @click="changeStatus(selectedTable, 'occupied')"
                                        :class="selectedTable.status === 'occupied' ? 'ring-2 ring-red-400 bg-red-50 text-red-700 border-red-300' : 'bg-white text-gray-600 border-gray-200 hover:bg-red-50 hover:border-red-200'"
                                        class="py-2 rounded-lg border text-xs font-medium transition-all duration-150">🔴 Occupied</button>
                                <button @click="changeStatus(selectedTable, 'reserved')"
                                        :class="selectedTable.status === 'reserved' ? 'ring-2 ring-amber-400 bg-amber-50 text-amber-700 border-amber-300' : 'bg-white text-gray-600 border-gray-200 hover:bg-amber-50 hover:border-amber-200'"
                                        class="py-2 rounded-lg border text-xs font-medium transition-all duration-150">🟠 Reserved</button>
                                <button @click="changeStatus(selectedTable, 'cleaning')"
                                        :class="selectedTable.status === 'cleaning' ? 'ring-2 ring-blue-400 bg-blue-50 text-blue-700 border-blue-300' : 'bg-white text-gray-600 border-gray-200 hover:bg-blue-50 hover:border-blue-200'"
                                        class="py-2 rounded-lg border text-xs font-medium transition-all duration-150">🔵 Cleaning</button>
                                <button @click="changeStatus(selectedTable, 'merged')"
                                        :class="selectedTable.status === 'merged' ? 'ring-2 ring-purple-400 bg-purple-50 text-purple-700 border-purple-300' : 'bg-white text-gray-600 border-gray-200 hover:bg-purple-50 hover:border-purple-200'"
                                        class="py-2 rounded-lg border text-xs font-medium transition-all duration-150">🟣 Merged</button>
                                <button @click="changeStatus(selectedTable, 'blocked')"
                                        :class="selectedTable.status === 'blocked' ? 'ring-2 ring-gray-400 bg-gray-100 text-gray-600 border-gray-300' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100 hover:border-gray-300'"
                                        class="py-2 rounded-lg border text-xs font-medium transition-all duration-150">⚫ Blocked</button>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-2">
                            <button @click="closeDetail" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Close</button>
                            <button class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">View Order</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    {{-- ===== NEW TABLE MODAL ===== --}}
    <template x-teleport="body">
        <div x-show="showNewTableModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="showNewTableModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 z-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Add New Table</h3>
                    <button @click="showNewTableModal = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Table Name</label>
                        <input type="text" placeholder="e.g. T17" class="w-full h-11 px-3 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all placeholder:text-gray-400">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Capacity</label>
                            <select class="w-full h-11 px-3 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                                <option>2</option>
                                <option>4</option>
                                <option>6</option>
                                <option>8</option>
                                <option>10</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Section</label>
                            <select class="w-full h-11 px-3 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                                <option>Main Dining</option>
                                <option>VIP</option>
                                <option>Bar</option>
                                <option>Patio</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Assign Waiter</label>
                        <select class="w-full h-11 px-3 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
                            <option>John D.</option>
                            <option>Sarah M.</option>
                            <option>Mike R.</option>
                            <option>Emma W.</option>
                            <option>Alex B.</option>
                        </select>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button @click="showNewTableModal = false" class="flex-1 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                        <button @click="showNewTableModal = false" class="flex-1 py-2.5 rounded-xl bg-blue-600 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">Add Table</button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
