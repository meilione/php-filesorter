<?php

//phpinfo();

include_once('stemmer.php');

echo PorterStemmer::Stem('January'). "\n";
echo PorterStemmer::Stem('February'). "\n";
echo PorterStemmer::Stem('March'). "\n";
echo PorterStemmer::Stem('April'). "\n";
echo PorterStemmer::Stem('May'). "\n";
echo PorterStemmer::Stem('June'). "\n";
echo PorterStemmer::Stem('Juli'). "\n";
echo PorterStemmer::Stem('August'). "\n";
echo PorterStemmer::Stem('October'). "\n";
echo PorterStemmer::Stem('November'). "\n";
echo PorterStemmer::Stem('December'). "\n";

phpinfo();

