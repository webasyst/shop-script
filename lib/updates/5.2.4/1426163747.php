<?php

//delete old files
foreach (array(
    'img/grey-noisy-bg.jpg',
	'lib/actions/reports/shopReportsProfit.action.php',
	'lib/actions/reports/shopReportsTop.action.php',
	'templates/actions/reports/ReportsProfit.html',
	'templates/actions/reports/ReportsTop.html'
) as $f) {
    waFiles::delete($this->getAppPath($f), true);
}