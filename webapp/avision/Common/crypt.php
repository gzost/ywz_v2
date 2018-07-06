<?php

//$str='bbcd\t1235';
//$key="ww12345671";
//$en_str=do_mencrypt($str, $key);
//$de_str=do_mdecrypt($en_str, $key);
//
//echo $en_str.'<br>'.$de_str;

 function do_mencrypt($input, $key)
     {
         //$input = str_replace("\n", "", $input);
         //$input = str_replace("\t", "", $input);
         //$input = str_replace("\r", "", $input);
         $key = substr(md5($key), 0, 24);
         $td = mcrypt_module_open('tripledes', '', 'ecb', '');
         $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
         mcrypt_generic_init($td, $key, $iv);
         $encrypted_data = mcrypt_generic($td, $input);
         mcrypt_generic_deinit($td);
         mcrypt_module_close($td);

         return trim(chop(base64_encode($encrypted_data)));
     }
     
     //$input - stuff to decrypt
     //$key - the secret key to use
     
     function do_mdecrypt($input, $key)
     {
         //$input = str_replace("\n", "", $input);
         //$input = str_replace("\t", "", $input);
         //$input = str_replace("\r", "", $input);
         $input = trim(chop(base64_decode($input)));
         $td = mcrypt_module_open('tripledes', '', 'ecb', '');
         $key = substr(md5($key), 0, 24);
         $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
         mcrypt_generic_init($td, $key, $iv);
         $decrypted_data = mdecrypt_generic($td, $input);
         mcrypt_generic_deinit($td);
         mcrypt_module_close($td);
         return trim(chop($decrypted_data));
 
    }   

?>