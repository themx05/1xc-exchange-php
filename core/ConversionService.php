<?php
class ConversionProvider{

    /**
     * FrConv Result Scheme: 
     * {
     *  "USD_EUR":1.3941
     * }
     */
    function consumeFrConvEndpoint(string $from, string $to):float {
        $q=$from."_".$to;
        $url = FR_CONV_URL."apiKey=".FR_CONV_API_KEY."&q=$q&compact=ultra";
        try{
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $raw = curl_exec($curl);
            curl_close($curl);
            if($raw == false){
                return -1;
            }
            $raw_decoded = json_decode($raw,true);
            if(isset($raw_decoded["status"]) && $raw_decoded["status"] == 400 ){
                return -1;
            }
            $decoded = $raw_decoded[$q];
            if(!isset($decoded)){
                return -1;
            }
            return $decoded;
        }catch(exception $e){ 
            return -1;
        }
    }

    function consumeCoinbaseEndpoint(string $from, string $to): float{
        $url = "https://api.coinbase.com/v2/exchange-rates?currency=$from";
        try{
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $raw = curl_exec($curl);
            curl_close($curl);
            if($raw == false){
                return -1;
            }
            $raw_decoded = json_decode($raw,true);
            if(isset($raw_decoded["data"])){
                if(isset($raw_decoded['data']['rates'])){
                    if(isset($raw_decoded['data']['rates'][$to])){
                        return floatval($raw_decoded['data']['rates'][$to]);
                    }
                }
            }
            return -1;
        }catch(exception $e){ 
            return -1;
        }
    }

    /**
     * FIXER Result Scheme :
     * {
     *  success: boolean;
     *  timestamp:number;
     *  base:"EUR"
     *  date:string;
     *  rates:{
     *  "$to":number;
     * }
     * }
     * 
     * BASE : USD;
     *   TO: EUR : USD-EUR = 0.9 =====> EUR - USD = 1/0.9 
        TO XOF: USD-XOF = 543  =====> XOF - USD = 1/543
        EUR - XOF = ?  
        1 USD font (0.9) EUR 
        1 USD font (543) XOF 
        Cela etant, 0.9 EUR font 543 XOF, 1 EUR font donc (543/0.9) XOF 
        Cela dit, pour deux Devises A et B a interchanger et en se basant sur une devise de base C 
        SOit Tca le taux de change C-A;
        Soit Tcb le taux de change C-B;
        [ Tab = Tcb / Tca ]
     */
    function consumeFixerEndpoint(string $from, string $to):float{
        $q=$from."_".$to;
        $symbols= $from.",".$to;
        $url = FIXER_URL."access_key=".FIXER_API_KEY."&symbols=$symbols";
        try{
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $raw = curl_exec($curl);
            if($raw == false){
                curl_close($curl);
                return -1;
            }
            $raw_decoded = json_decode($raw,true);
            if($raw_decoded["success"] === false){
                curl_close($curl);
                return -1;
            }
            $decoded = $raw_decoded['rates'];
            $rate_base_from = $decoded[$from];
            $rate_base_to = $decoded[$to];
            $rate_from_to = $rate_base_to / $rate_base_from; 
            curl_close($curl);
            return $rate_from_to;
        }catch(exception $e){
            return [];
        }
    }

    function convert(array $data):array {             
        try{
            $rate = $this->consumeFrConvEndpoint($data['source'],$data['dest']);
            if($rate == -1){
                $rate = $this->consumeCoinbaseEndpoint($data['source'], $data['dest']);
                if($rate == -1){
                    $rate = $this->consumeFixerEndpoint($data['source'],$data['dest']);
                    if($rate == -1){
                        return [];
                    }
                }
            }
            $response = [
                "source" => $data['source'],
                "dest" => $data['dest'],
                "rate" => $rate,
                "amount" => $data['amount'],
                'converted' => $data['amount'] * $rate 
            ];
            return $response;
        }catch(Exception $se){
            return [];
        }
    }

}
?>