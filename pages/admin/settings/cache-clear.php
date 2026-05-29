<?php
cache_clear_all();
flash('success', 'Cache cleared successfully.');
redirect(url('/admin/settings'));
