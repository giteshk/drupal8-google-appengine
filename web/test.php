<?php

try {
  $json = ['hello' => 'world'];
  // print "about to read \n";
  // var_dump(file_get_contents("gcs://petstore_swagger.json"));
  print "about to write \n";
  file_put_contents("gcs://gitesh-test-spec.json", json_encode($json));
  file_put_contents("gcs-private://gitesh-test-spec-private.json", json_encode($json));
  print "Done writing \n";
}catch(Exception $ex){
  var_dump($ex);
}
