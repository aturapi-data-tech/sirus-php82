import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse'
import mask from '@alpinejs/mask'

import toastr from 'toastr'
import 'toastr/build/toastr.min.css'

window.toastr = toastr

window.Alpine = Alpine;
Alpine.plugin(collapse)
Alpine.plugin(mask)
// Alpine.start();

// PENTING: JANGAN Alpine.start()
// Livewire yang akan start Alpine saat boot
