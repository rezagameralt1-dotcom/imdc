<?php
namespace App\Http\Controllers;
class HealthController extends Controller {
    public function __invoke(){ return response()->json(["ok"=>true,"time"=>now()]); }
}