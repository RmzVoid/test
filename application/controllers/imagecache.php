<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class ImageCache extends CI_Controller
{
	public function index($dims, $img)
	{
		list($w, $h) = explode("x", $dims);
		
		// если переданы неверные размеры или название файла
		if( $w<=0 || $h<=0 || $w>2048 || $h>2048 || empty($img) )
			return;
		
		// сгенерируем путь к кэшированному файлу изображения
		$cached_image_path = "./application/cache/".$img."_".$w."x".$h.".jpg";

		// если файл не существует, отмасштабируем, сохраним и отдадим юзеру
		if( file_exists($cached_image_path) === FALSE )
		{
			// физ. путь к файлу запрашиваемого изображения
			$image_path = "./images/$img.jpg";
			
			// загрузим изображение библиотекой GD2 и получим его размеры
			$gd_image = imagecreatefromjpeg($image_path);
			$real_w = imagesx($gd_image);
			$real_h = imagesy($gd_image);
			
			// создадим болванку для отмаштабированного изображения
			$gd_image_resized = imagecreatetruecolor($w, $h);
			
			// проверим необходимость обрезки
			// соотношение сторон исходного изображения, c точностью до 2-х знаков
			$real_ratio = round($real_w/$real_h, 2);
			// соотношение сторон запрашиваемого изображения
			$ratio = round($w/$h, 2);
			
			// если пропорции отличаются
			// отбрежем исходное изображение так, чтобы после масштабирования
			// изображение было корректно обрезанным и отцентрированным
			$X = 0; $Y = 0;
			$W = $real_w; $H = $real_h;
			
			if( $real_ratio!=$ratio )
			{
				// найдем большую сторону
				$Ww = $real_w / $w;
				$Hh = $real_h / $h;
				if( $Ww > $Hh )
				{
				    $W = floor($w * $Hh);
                    $X = ($real_w - $W) >> 1;
				}
				else
				{
				    $H = floor($h * $Ww);
                    $Y = ($real_h - $H) >> 1;
				}

			}
			
           	imagecopyresampled($gd_image_resized, $gd_image, 0, 0, $X, $Y, $w, $h, $W, $H);
			
			// сохраним отмасштабированное изображение в кэшфайл
			imagejpeg($gd_image_resized, $cached_image_path);
			
			// освободим память
			imagedestroy($gd_image);
			imagedestroy($gd_image_resized);
		}
		
		$data['image_path'] = $cached_image_path;
		$this->load->view('imagecache', $data);
	}
}
