<?php
header('Content-Type: application/json');
$books = glob("books/*.epub");
if ($books === false) {
    $books = [];
}
echo json_encode($books);
