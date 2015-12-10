<?php defined('SYSPATH') or die('No direct script access.');
/**
 *  Handle 400 error
 *  @author Alexander Demyashev (develop@demyashev.com)
 */
class HTTP_Exception_400 extends Kohana_HTTP_Exception_400 {
 
    public function get_response()
    {
        Kohana_Exception::log($this);

        if (Kohana::$environment >= Kohana::DEVELOPMENT)
        {
            return parent::get_response();
        }
        else 
        {
            /*

            // send debug info to developer
            $to = Kohana::$config->load('main.site.author.email');
            $subject = "400 Bad Request";
            $message = "{$this->getMessage()}\n\n{$this->getFile()} on line {$this->getLine()}";
            $header  = "From: robot@" . Arr::get($_SERVER, 'SERVER_NAME'). "\r\n";

            @mail($to, $subject, $message, $header);

            */

            $view = View::factory('templates/errors/default');
            $view->set('title', "400 Bad Request");
            $view->set('message', "{$this->getMessage()}");
             
            $response = Response::factory()
                ->status(400)
                ->body($view->render());
     
            return $response;
        }
    }
}