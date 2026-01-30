import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse'

import toastr from 'toastr'
import 'toastr/build/toastr.min.css'

window.toastr = toastr

window.Alpine = Alpine;
Alpine.plugin(collapse)
// Alpine.start();

// PENTING: JANGAN Alpine.start()
// Livewire yang akan start Alpine saat boot