<?php

namespace Elyzin\Core;

use Config;

class Fallacy
{
	private static $etype = [
			1 => 'E_ERROR',
			2 => 'E_WARNING',
			4 => 'E_PARSE',
			8 => 'E_NOTICE',
			16 => 'E_CORE_ERROR',
			32 => 'E_CORE_WARNING',
			64 => 'E_COMPILE_ERROR',
			128 => 'E_COMPILE_WARNING',
			256 => 'E_USER_ERROR',
			512 => 'E_USER_WARNING',
			1024 => 'E_USER_NOTICE',
			2048 => 'E_STRICT',
			4096 => 'E_RECOVERABLE_ERROR',
			8192 => 'E_DEPRECATED',
			16384 => 'E_USER_DEPRECATED',
			32767 => 'E_ALL'
		];

	public static function init()
	{
		set_error_handler('Fallacy::handle');
		register_shutdown_function('Fallacy::error_alert');
	}

	public static function handle($code, $err, $origin, $line, $dump)
	{
		$time = new \DateTime("now", new \DateTimeZone(getenv('TIMEZONE')));
		if(\getenv('ERROR_MAIL')){
			//echo getenv('SITEMAIL');
		}

		if(\getenv('ERROR_SCREEN')){
			ob_start();
			echo "
			<!DOCTYPE html>
			<html>
			<title>" . getenv('SITENAME') . " : Error</title>
			<body style='background: #FBFBFB;'>
			<div style='font-family: Verdana; display: flex; position: fixed; width: 100vw; height: 100vh; overflow: hidden; justify-content: center; align-items: center;'>
			<div style='background: white; border: 1.5px solid #F00; border-radius: 3px; box-shadow: 0px 0px 0px 3px rgba(255,0,0,0.15);'>
			<div style='background: rgba(255,0,0,1); text-align: center; color: #FFF; font-weight: 700; letter-spacing: -2px; font-size: 16px; padding: 10px;'>INTERNAL ERROR!</div>
			<table style='padding: 10px; font-size: 12px;'>
			<tr><td>Type</td><td>:</td><td><b>" . self::$etype[$code] . "</b></td></tr>
			<tr><td>Error</td><td>:</td><td><b>" . $err . "</b></td></tr>
			<tr><td>Origin</td><td>:</td><td><b>" . explode(DRT,$origin)[1] . "</b></td></tr>
			<tr><td>Line No</td><td>:</td><td><b>" . $line."</b></td></tr>
			</table>
			</div>
			</div>
			</body>
			</html>
			";
			ob_end_flush();
		}

		if(\getenv('ERROR_LOG')){
			$logline = "Error Code: " .self::$etype[$code] ." ". $err ." @ ".$time->format('h:i:sa d M Y');
			$logline .= "\n";
			error_log($logline, 3, Config::path('log').'error.log');
		}
		die();
	}

	public static function error_alert()
	{
		if (is_null($e = error_get_last()) === false) {
			ob_start();
			echo '<style type="text/css">
			body {overflow: hidden; font-family:"Lucida Sans Unicode", "Lucida Grande", sans-serif; margin: 0; padding:0; }
			.tg-wrap {position: absolute; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; background: rgba(0,0,0,0.4); z-index: 9999;}
			.tg {box-shadow: 0 0 5px #FF0000;border-collapse:collapse;border-spacing:0;margin:0px auto; background: #FFF; border-radius: 2px; width: 300px; max-width: 300px;}
			.tg td{font-size:13px;padding:10px;border-style:solid;border-width:1px;overflow:hidden;border-color:transparent;}
			.tg th{font-size:14px;font-weight:700; color:red; background: #f4f4f4; padding:10px;border-style:solid;border-width:1px;overflow:hidden;word-break:normal;border-color:transparent;}
			.tg .tg-td{vertical-align:top; border-bottom: 1px solid #EEE;}
			.tg tr:last-child .tg-td{border-bottom: none;}
			@media screen and (max-width: 767px) {.tg {width: auto !important;}.tg col {width: auto !important;}.tg-wrap {overflow-x: auto;-webkit-overflow-scrolling: touch;margin: auto 0px;}}</style>
			<div class="tg-wrap"><table class="tg"><tr><th class="tg-td" colspan="2">An error has been occurred!</th></tr>';
			$e['message'] = explode(" in ", $e['message'])[0];
			$e['file'] = $e['file'];
			$e['type'] = $this->etype[$e['type']];
			foreach ($e as $key => $value) {
				echo '<tr><td class="tg-td">' . ucwords($key) . ':</td>';
				echo '<td class="tg-td">' . $value . '</td></tr>';
			}
			echo '<tr><td class="tg-td" colspan="2" style="text-align: center; font-weight: 700; font-size: 12px;">Please <a href="mailto:hi@eff.one">contact support</a> for assistance.</th></tr></table></div>';
			ob_end_flush();
			die();
		}
	}
}