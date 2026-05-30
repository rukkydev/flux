<?php
middleware('admin');
admin_logout();
flash('success', 'Logged out.');
redirect(url('/admin/login'));
