<?php
    /**
     * JCH Optimize - Performs several front-end optimizations for fast downloads
     *
     * @package   jchoptimize/wordpress-platform
     * @author    Samuel Marshall <samuel@jch-optimize.net>
     * @copyright Copyright (c) 2020 Samuel Marshall / JCH Optimize
     * @license   GNU/GPLv3, or later. See LICENSE file
     *
     * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
     */

    defined( '_JCH_EXEC' ) or die( 'Restricted Access' );

    $subscribe_url = 'https://www.jch-optimize.net/subscribes/subscribe-wordpress/levels.html';

?>

    <div id="help-section" class="container" style="box-sizing: border-box;">
        <div class="card mt-0">
            <div class="grid">
                <div class="g-col-12 g-col-lg-5">
                    <div class="card-body">
                        <img class="img-fluid" alt="JCH Optimize" src="<?= JCH_PLUGIN_URL ?>media/images/logo.png"/>
                    </div>
                </div>
                <div class="g-col-12 g-col-lg-7">
                    <div class="card-body">
                        <p class="card-text">
                            <?= __( 'This plugin speeds up your website by performing a number of front end optimizations to your website automatically. These optimizations reduce both your webpage size and the number of http requests required to download your webpages and results in reduced server load, lower bandwidth requirements, and faster page loading times.', 'jch-optimize' ) ?>
                        </p>
                        <?php if(!JCH_PRO): ?>
                            <a href="https://www.jch-optimize.net/subscribes/subscribe-wordpress/levels.html"
                               class="btn btn-success" target="_blank">Upgrade To Pro</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="grid" style="--bs-gap: 0;">
                <div class="g-col-12 g-col-lg-3">
                    <div class="card-header">
                        <h2><?= __( 'Major Features', 'jch-optimize' ) ?></h2>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><?= __( 'Optimize CSS/JS/HTML', 'jch-optimize' ) ?></li>
                        <li class="list-group-item"><?= __( 'Lazy-Load', 'jch-optimize' ) ?></li>
                        <li class="list-group-item"><?= __( 'Page Cache', 'jch-optimize' ) ?></li>
                        <li class="list-group-item"><?= __( 'CDN Support', 'jch-optimize' ) ?></li>
                        <li class="list-group-item"><?= __( 'Http/2 Preload', 'jch-optimize' ) ?></li>
                        <li class="list-group-item"><?= __( 'Htaccess Optimization', 'jch-optimize' ) ?></li>
                        <li class="list-group-item"><?= __( 'Optimize Images', 'jch-optimize' ) ?> <span
                                    class="badge bg-danger rounded-pill">Pro Version Only</span></li>
                    </ul>
                </div>
                <div class="g-col-12 g-col-lg-9">
                    <img class="img-fluid img-thumbnail p-4" alt="Major Features"
                         src="<?=JCH_PLUGIN_URL?>media/images/major_features.png"/>
                </div>
            </div>
        </div>

        <div class="card">
            <img src="<?=JCH_PLUGIN_URL?>media/images/how_to_use.png" class="card-img-top img-thumbnail p-4"
                 alt="How to use"/>
            <div class="card-body">
                <h2 class="card-title"><?= __( 'How To Use', 'jch-optimize' ) ?></h2>
            </div>
            <ol class="list-group list-group-flush list-group-numbered">
                <li class="list-group-item d-flex">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold mb-2">Deactivate Page Cache</div>
                        Deactivate all Page Cache features and plugins while configuring so you can immediately view
                        your
                        changes.
                    </div>
                </li>
                <li class="list-group-item d-flex">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold mb-2">Configure Automatic Settings</div>
                        Enable and select an Automatic Setting to optimize your CSS/JavaScript files. The higher the
                        setting
                        the higher the level of Optimization but greater the risks of conflicts. Check your pages for
                        any
                        conflicts before continuing.
                    </div>
                </li>
                <li class="list-group-item d-flex">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold mb-2">Resolve Conflicts</div>
                        You can exclude files from the optimization process that don't work well. You'll find these
                        settings
                        under the Configurations tab on the CSS and JavaScript sub vertical tabs.
                    </div>
                </li>
                <li class="list-group-item d-flex">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold mb-2">Enable Other Features</div>
                        Once you're satisfied with the above steps, Activate other features based on the needs of the
                        site
                        one at a time, checking the page each time for conflicts.
                    </div>
                </li>
                <li class="list-group-item d-flex">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold mb-2">Enable Page Caching</div>
                        After configuring and resolving any conflicts, you can re-enable Page Cache for best
                        optimization
                        results. The plugin is also compatible with other popular Page Cache plugins.
                    </div>
                </li>
            </ol>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="card-title"><?= __( 'Support', 'jch-optimize' ) ?></h2>
                <p><?= sprintf( wp_kses( __( 'First check out the <a href="%1$s" target="_blank">documentation</a>, particularly the <a href="%2$s" target="_blank">Getting Started</a> and <a href="%3$s" target="_blank">How to optimize your site</a> pages on the plugin\'s website to learn how to use and configure the plugin.', 'jch-optimize' ), [
		    'a' => [
			    'href'   => [],
			    'target' => []
		    ]
	    ] ), esc_url( 'https://www.jch-optimize.net/documentation.html' ), esc_url( 'https://www.jch-optimize.net/documentation/getting-started.html' ), esc_url( 'https://www.jch-optimize.net/documentation/optimizing-your-site.html' ) ) ?></p>
                <p><?= sprintf( wp_kses( __( 'Read <a href="%s" target="_blank">Here</a> for some troubleshooting guides to resolve some common issues users generally encounter with using the plugin.', 'jch-optimize' ), [
		    'a' => [
			    'href'   => [],
			    'target' => []
		    ]
	    ] ), esc_url( 'https://www.jch-optimize.net/documentation/troubleshooting.html' ) ) ?></p>
                <p><?= sprintf( wp_kses( __( 'You\'ll need a subscription to submit tickets to get premium support in configuring the plugin to resolve conflicts so <a href="%1$s" target="_blank">subscribe</a> to <em>JCH Optimize Pro for WordPress</em> and access your account to submit a ticket. Otherwise you can use the <a href="%2$s" target="_blank" >WordPress support system</a> to submit support requests.', 'jch-optimize' ), [
		    'a'  => [
			    'href'   => [],
			    'target' => []
		    ],
		    'em' => []
	    ] ), esc_url( $subscribe_url ), esc_url( 'https://wordpress.org/support/plugin/jch-optimize/' ) ) ?></p>
            </div>
        </div>
        <p class="notice notice-info"
           style="margin: 1em 0; padding: 10px 12px"><?= sprintf( wp_kses( __( 'If you use this plugin please consider posting a review on the plugin\'s <a href="%s" target="_blank" >WordPress page</a>. If you\'re having problems, please submit for support and give us a chance to resolve your issues before reviewing. Thanks.', 'jch-optimize' ), [
		    'a' => [
			    'href'   => [],
			    'target' => []
		    ]
	    ] ), esc_url( 'https://wordpress.org/support/plugin/jch-optimize/reviews/' ) ) ?></p>

    </div>