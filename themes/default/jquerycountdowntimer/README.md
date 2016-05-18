[jquery-countdownTimer Plugin](http://plugins.jquery.com/countdownTimer/)- Reverse count down jQuery plugin.
===============================

## <a id="Introduction"></a>Introduction

**countdownTimer** is a reverse count down jQuery plugin for displaying countdown as per your need. It also displays current local time.

## [Help the project](https://pledgie.com/campaigns/23663)

<a href='https://pledgie.com/campaigns/23663'><img alt='Click here to lend your support to: jQuery countdownTimer Plugin and make a donation at pledgie.com !' src='https://pledgie.com/campaigns/23663.png?skin_name=chrome' border='0' ></a>

This project is looking for help! [You can donate to the ongoing pledgie campaign](https://pledgie.com/campaigns/23663)
and help spread the word. If you've used the plugin, or plan to use, consider a donation - any amount will help.

You can find the plan for how to spend the money on the [pledgie page](https://pledgie.com/campaigns/23663).

## Getting Started

Include jQuery, the plugin and its css file on a page. Add a div and span element. Then call the `countdowntimer` method with the required options on the span element id.

```html
<script type="text/javascript" src="jquery-2.0.3.js"></script>
<script type="text/javascript" src="jquery.countdownTimer.js"></script>
<link rel="stylesheet" type="text/css" href="jquery.countdownTimer.css" />

<div id="countdowntimer"><span id="future_date"><span></div>

<script type="text/javascript">
	  $(function(){
	    $('#future_date').countdowntimer({
	      dateAndTime : "2018/01/01 00:00:00",
	      size : "lg"
	    });
	  });
</script>
```
For more information on how to use different options, [check the documentation](https://raw.github.com/harshen/jquery-countdownTimer/master/DOCS/jQuery_countdownTimer_Documentation).

Also refer the demos for more clarity.

### Dependencies

jQuery greater than or equal to version 1.5.

## <a id="Support"></a>Support

Please post bug reports and other contributions (enhancements, features) to the GitHub issue tracker.

## <a id="License"></a>License

Copyright (c) 2014 Harshen Pandey
Licensed under the MIT and GPLv3 license.
