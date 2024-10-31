<div class="wrap">
    <?php screen_icon(); ?>
    <h2>SalesBinder Settings</h2>
    <form method="post" action="options.php">
        <?php settings_fields('salesbinder_settings'); ?>
        <?php do_settings_sections('salesbinder'); ?>
        <?php submit_button(); ?>
    </form>
</div>