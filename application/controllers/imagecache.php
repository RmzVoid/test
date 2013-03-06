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
           	
           	if( $w<$W || $h<$H )
           		// unsharp mask
           		$this->unsharp_mask($gd_image_resized, 100, 10, 0); 
			
			// сохраним отмасштабированное изображение в кэшфайл
			// ob для синхронизации функции imagejpeg
			// иначе файл не успевает сформироваться до вывода изображения
			ob_start();
			imagejpeg($gd_image_resized);
			file_put_contents($cached_image_path, ob_get_contents());
			ob_end_clean();
			
			// освободим память
			imagedestroy($gd_image);
			imagedestroy($gd_image_resized);
		}
		
		$data['image_path'] = $cached_image_path;
		$this->load->view('imagecache', $data);
	}
	
	private function unsharp_mask($img, $amount, $radius, $threshold)   
	{  
		///////////////////////////////////////////////////////////////////////   
		////    Unsharp mask algorithm by Torstein Hønsi 2003-07.   
		////             thoensi_at_netcom_dot_no.   
		////               Please leave this notice.   
		///////////////////////////////////////////////////////////////////////   

	    // Attempt to calibrate the parameters to Photoshop:  
	    if ($amount > 500)    $amount = 500;  
	    $amount = $amount * 0.016;  
	    if ($radius > 50)    $radius = 50;  
	    $radius = $radius * 2;  
	    if ($threshold > 255)    $threshold = 255;  
	      
	    $radius = abs(round($radius));     // Only integers make sense.  
	    if ($radius == 0) {  
	        return $img; imagedestroy($img); break;        }  
	    $w = imagesx($img); $h = imagesy($img);  
	    $imgCanvas = imagecreatetruecolor($w, $h);  
	    $imgBlur = imagecreatetruecolor($w, $h);  
	      
	
	    // Gaussian blur matrix:  
	    //                          
	    //    1    2    1          
	    //    2    4    2          
	    //    1    2    1          
	    //                          
	
	    if (function_exists('imageconvolution'))  // PHP >= 5.1
		{   
            $matrix = array(   
	            array( 1, 2, 1 ),   
	            array( 2, 4, 2 ),   
	            array( 1, 2, 1 )  
	        );
	        imagecopy ($imgBlur, $img, 0, 0, 0, 0, $w, $h);  
	        imageconvolution($imgBlur, $matrix, 16, 0);   
	    }   
	    else
		{   
		    // Move copies of the image around one pixel at the time and merge them with weight  
		    // according to the matrix. The same matrix is simply repeated for higher radii.  
	        for ($i = 0; $i < $radius; $i++)    {  
	            imagecopy ($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left  
	            imagecopymerge ($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right  
	            imagecopymerge ($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center  
	            imagecopy ($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);  
	
	            imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up  
	            imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down  
	        }  
	    }  
	
	    if( $threshold>0 )
		{  
	        // Calculate the difference between the blurred pixels and the original  
	        // and set the pixels  
	        for ($x = 0; $x < $w-1; $x++) // each row
			{ 
	            for ($y = 0; $y < $h; $y++) // each pixel
				{  
	                $rgbOrig = ImageColorAt($img, $x, $y);  
	                $rOrig = (($rgbOrig >> 16) & 0xFF);  
	                $gOrig = (($rgbOrig >> 8) & 0xFF);  
	                $bOrig = ($rgbOrig & 0xFF);  
	                  
	                $rgbBlur = ImageColorAt($imgBlur, $x, $y);  
	                  
	                $rBlur = (($rgbBlur >> 16) & 0xFF);  
	                $gBlur = (($rgbBlur >> 8) & 0xFF);  
	                $bBlur = ($rgbBlur & 0xFF);  
	                  
	                // When the masked pixels differ less from the original  
	                // than the threshold specifies, they are set to their original value.  
	                $rNew = (abs($rOrig - $rBlur) >= $threshold)   
	                    ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))   
	                    : $rOrig;  
	                $gNew = (abs($gOrig - $gBlur) >= $threshold)   
	                    ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))   
	                    : $gOrig;  
	                $bNew = (abs($bOrig - $bBlur) >= $threshold)   
	                    ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))   
	                    : $bOrig;  
	                              
	                if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew))
					{  
                        $pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew);  
                        ImageSetPixel($img, $x, $y, $pixCol);  
	                }  
	            }  
	        }  
	    }  
	    else
		{  
	        for ($x = 0; $x < $w; $x++) // each row
			{
	            for ($y = 0; $y < $h; $y++) // each pixel
				{  
	                $rgbOrig = ImageColorAt($img, $x, $y);  
	                $rOrig = (($rgbOrig >> 16) & 0xFF);  
	                $gOrig = (($rgbOrig >> 8) & 0xFF);  
	                $bOrig = ($rgbOrig & 0xFF);  
	                  
	                $rgbBlur = ImageColorAt($imgBlur, $x, $y);  
	                  
	                $rBlur = (($rgbBlur >> 16) & 0xFF);  
	                $gBlur = (($rgbBlur >> 8) & 0xFF);  
	                $bBlur = ($rgbBlur & 0xFF);  
	                  
	                $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;  
	                    if($rNew>255){$rNew=255;}  
	                    elseif($rNew<0){$rNew=0;}  
	                $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;  
	                    if($gNew>255){$gNew=255;}  
	                    elseif($gNew<0){$gNew=0;}  
	                $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;  
	                    if($bNew>255){$bNew=255;}  
	                    elseif($bNew<0){$bNew=0;}  
	                $rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew;  
	                    ImageSetPixel($img, $x, $y, $rgbNew);  
	            }  
	        }  
	    }
	    
	    imagedestroy($imgCanvas);  
	    imagedestroy($imgBlur);  
	      
	    return $img;  
	}	
}
