<div class="contents">
    <div>
        <div class="image">
            <img class="lazy" data-src="<?php echo Registry::load('config')->site_url.'assets/files/defaults/welcome.png'.$cache_timestamp; ?>" />
        </div>
        <div class="text">
            <span class="title"><?php echo Registry::load('strings')->welcome_screen_heading ?></span>
            <span class="sub_title"><?php echo Registry::load('strings')->welcome_screen_message ?></span>
            <span class="footer"><?php echo Registry::load('strings')->welcome_screen_footer_text ?></span>
        </div>
        
    <div class="go_back_home d-block d-md-none">
        <div>
            <svg width="20px" height="20px" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg">
                <path fill="currentColor" d="M224 480h640a32 32 0 1 1 0 64H224a32 32 0 0 1 0-64z" />
                <path fill="currentColor" d="m237.248 512 265.408 265.344a32 32 0 0 1-45.312 45.312l-288-288a32 32 0 0 1 0-45.312l288-288a32 32 0 1 1 45.312 45.312L237.248 512z" />
            </svg>
            <span><?php echo Registry::load('strings')->go_back ?></span>
        </div>
    </div>
        <?php
        if (isset($site_adverts['welcome_screen'])) {
            $site_advert = $site_adverts['welcome_screen'];
            $advert_css = 'max-height:'.$site_advert['site_advert_max_height'].'px;';

            if (!empty($site_advert['site_advert_min_height'])) {
                $advert_css .= 'min-height:'.$site_advert['site_advert_min_height'].'px;';
            }
            ?>

            <div class="site_advert_block" style="<?php echo $advert_css; ?>">
                <div>
                    <?php echo $site_advert['site_advert_content']; ?>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</div>