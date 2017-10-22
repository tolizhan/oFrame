<?php
namespace demo;
class myimplements implements myinterface{
	public function getData($data){
		return $data.'test';
	}

	public function test2(){
		return 'test2';
	}
}