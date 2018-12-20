<?php

//rmdir("specstore://gitesh1/gitesh2/gitesh3");
//mkdir("specstore://gitesh1/gitesh2/gitesh3", 0777, true);
//rename("specstore://gitesh1/gitesh2/gitesh3", "specstore://gitesh1/gitesh2/gitesh5");
//copy("specstore://product_spec", "specstore://gitesh1/gitesh2/product_spec");

if(is_dir("specstore://product_spec")) {
  print "is directory \n";
} else {
  print "not directory \n";
}
