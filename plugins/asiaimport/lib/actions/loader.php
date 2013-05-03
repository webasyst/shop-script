<?php
$data = file_get_contents( 'http://asia-fashion-wholesale.com/welcome/women-dress-new-update/all-selling.php' );
$data = str_replace( array('</td> <td>', '<tr><td>', '</td> </tr>', " bgcolor='#EEF0EE'", "<a target='_blank' href='", '</a>'), array(';', '', PHP_EOL, '', '', ''), $data);
$data = str_replace(array('/welcome/', "<tr><td> ", "'>", 'In stock', "<font color='#FF0000;[ Restocking ]</font>", '<tr ><td> '), array('', '', ';', '10', '0', ''), $data);
file_put_contents( 'data.html', $data );
