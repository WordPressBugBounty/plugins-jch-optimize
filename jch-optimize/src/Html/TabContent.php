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

namespace JchOptimize\Html;

class TabContent
{
    public static function start(): string
    {
        return <<<HTML
<div class="tab-content">
	<div style="display:none">
		<fieldset>
			<div>
HTML;
    }

    public static function addTab(string $id, bool $active = false): string
    {
        $active = $active ? ' active' : '';

        return <<<HTML
			</div>
		</fieldset>
	</div>		
	<div class="tab-pane{$active}" id="{$id}">
		<fieldset style="display: none;">
			<div>
HTML;
    }

    public static function addSection(string $header = '', string $description = '', string $class = ''): string
    {
        if (! empty($header)) {
            $header = <<<HMTL
<legend>{$header}</legend>
HMTL;
        }

        return <<<HTML
			</div>
		</fieldset>
		<fieldset class="jch-group">
			{$header}
			<div class="{$class}"><p><em>{$description}</em></p></div>
			<div>		
HTML;
    }

    public static function end(): string
    {
        return <<<HTML
			</div>
		</fieldset>
	</div>
</div>
HTML;
    }
}
