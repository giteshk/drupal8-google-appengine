<?php

//print \Drupal::service('file_system')->realpath("specstore://abcd/1231231") .  "\n" ;

try {
//foreach(['core', 'modules', 'themes', 'robots.txt', 'web.config'] as $item) {
//  var_dump("$item : " . stat($item)['mode']);
//}
//  var_dump(file_scan_directory("specstore://", '/.*/'));
//$json = json_decode(file_get_contents("specstore://gitesh-test-spec"), true);
//$json = json_decode(file_get_contents("https://petstore.swagger.io/v2/swagger.json"), true);
//$json['info']['X-abcd'] = 'abcd';
//file_put_contents("specstore://gitesh-test-spec", json_encode($json));
//var_dump(file_get_contents("specstore://gitesh-test-spec"));
  $json = json_decode(file_get_contents("specstore://gitesh-test-spec"), true);
  $json['info']['x-test'] = 'test.php';
  $json['paths']['/pet']['post']['summary'] .= "Updated on " . date('c');
file_put_contents("specstore://abcd2/gitesh-test-spec", json_encode($json));
  file_put_contents("specstore://gitesh/gitesh-test-spec", json_encode($json));

  //var_dump(dir("specstore://"));
//  mkdir("specstore://gitesh123");
//  file_put_contents("specstore://gitesh123/petstore", file_get_contents("https://petstore.swagger.io/v2/swagger.json"));
  //var_dump(lstat("specstore://"));
//  var_dump(stat("https://raw.githubusercontent.com/giteshk/apigee_devportal_extras/master/README.md"));
}catch (Exception $e){
  var_dump($e->getMessage());
}
