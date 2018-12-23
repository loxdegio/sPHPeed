# sPHPeed
Simple, fast and lightweight MVC PHP framework

## Use example

To use sPHPeed you can put a code like this in your index.php.

To add new routes you can use the method App::addRoute($method, $path, $handler)

```
require_once 'path/to/vendor/autoload.php';

$app = new sPHPeed\App();

$app->addRoute('GET', '/index', 'MainController::index');

$app->addRoute('POST', '/login', 'UserController::login');

$app->run();

```

## Controllers
Any new controller need to extend Controller class of the core

## Twig

Extending your own controller it would be included the ability to render Twig templates

```

class MyController extends sPHPeed\Controller {
	
	public function genericHandlerMethod(Neat\Http\Request $request, $var1, $var2) {
		return $this->twig->render('mytemplate.twig.html');
	}
	
}

```
