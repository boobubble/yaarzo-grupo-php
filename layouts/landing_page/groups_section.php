<section class="groups_list" id="groups">

    <div>
        <div class="heading">
            <div class="container">
                <div class="row">
                    <div class="col-lg-5 mx-auto">
                        <h2><?php echo Registry::load('strings')->landing_page_groups_section_heading; ?></h2>
                        <p>
                            <?php echo nl2br(Registry::load('strings')->landing_page_groups_section_description); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="groups">
            <div class="container">

                <?php
                if (Registry::load('settings')->categorize_groups === 'yes') {
                    $columns = $join = $where = null;
                    $columns = [
                        'group_categories.group_category_id',
                        'group_categories.category_name',
                        'group_categories.group_category_image'
                    ];
                    $where["group_categories.disabled[!]"] = 1;

                    $join["[>]group_categories_roles"] = ["group_categories.group_category_id" => "group_category_id"];

                    $where["AND #cat_access"]["OR"] = [
                        "group_categories.access_restricted" => 0,
                        "group_categories_roles.site_role_id" => Registry::load('current_user')->site_role
                    ];

                    $where["ORDER"] = ["group_categories.category_order" => "ASC"];

                    $group_categories = DB::connect()->select('group_categories', $join, $columns, $where);
                    $language_id = Registry::load('current_user')->language;
                    ?>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="groups_categories_list">
                                <?php if (isset($group_categories[0])) {
                                    ?>
                                    <div class="active load_groups">
                                        <span class="cat_name"><?php echo Registry::load('strings')->all; ?></span>
                                    </div>
                                    <?php
                                } ?>
                                <?php foreach ($group_categories as $group_category) {

                                    $cat_info = data_cache(['folder' => 'category_trans/'.$group_category['group_category_id'], 'filename' => $language_id, 'method' => 'get', 'fs_cache' => true]);

                                    if (!empty($cat_info)) {
                                        if (isset($cat_info['category_name']) && !empty($cat_info['category_name'])) {
                                            $group_category['category_name'] = $cat_info['category_name'];
                                        }

                                    }

                                    $category_img = get_img_url(['from' => 'group_categories', 'image' => $group_category['group_category_image']]);
                                    ?>
                                    <div class="load_groups" category_id="<?php echo $group_category['group_category_id']; ?>">
                                        <span><img src="<?php echo $category_img; ?>" /></span>
                                        <span class="cat_name"><?php echo $group_category['category_name']; ?></span>
                                    </div>
                                    <?php
                                } ?>
                            </div>
                        </div>
                    </div>
                    <?php
                } ?>
                <div class="row row-cols-1 row-cols-md-4 g-4 landing_page_groups">
                    <?php include('layouts/landing_page/groups.php'); ?>
                </div>

                <?php
                $site_advert = DB::connect()->rand("site_advertisements",
                    ['site_advertisements.site_advert_min_height', 'site_advertisements.site_advert_max_height',
                        'site_advertisements.site_advert_content'],
                    ["site_advertisements.site_advert_placement" => 'landing_page_groups_section', "site_advertisements.site_role_restricted" => 0, "site_advertisements.disabled" => 0, "LIMIT" => 1]
                );
                if (isset($site_advert[0])) {
                    $site_advert = $site_advert[0];
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

    </div>
</section>