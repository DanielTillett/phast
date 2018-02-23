RUN = docker run -it -v $(shell pwd):/data -w /data $(shell cat .docker-image-id)

CLOSURE_COMPILER_VERSION = 20180204
CLOSURE_COMPILER_URL = https://dl.google.com/closure-compiler/compiler-20180204.tar.gz
CLOSURE_COMPILER = vendor/closure-compiler-v$(CLOSURE_COMPILER_VERSION).jar


.PHONY : all test test-local update docker


all : vendor/autoload.php src/Common/phast-js-env.js

clean :
	rm -f src/Common/phast-js-env.js

test : all docker
	$(RUN) vendor/bin/phpunit

test-local : all
	vendor/bin/phpunit

update : all
	vendor/composer.phar update

docker : .docker-image-id


src/Common/phast-js-env.js : src/Common/phast-js-env.src.js node_modules $(CLOSURE_COMPILER)
	node_modules/.bin/babel $< -o $@~
	java -jar $(CLOSURE_COMPILER) --js $@~ --js_output_file $@


vendor/autoload.php : vendor/composer.phar composer.json composer.lock
	vendor/composer.phar install

vendor/composer.phar :
	mkdir -p vendor
	wget -O $@~ https://github.com/composer/composer/releases/download/1.6.3/composer.phar
	chmod +x $@~
	mv $@~ $@


node_modules : package.json package-lock.json
	npm install


$(CLOSURE_COMPILER) :
	wget -O- '$(CLOSURE_COMPILER_URL)' | tar xzf - -C vendor $(notdir $(CLOSURE_COMPILER))


.docker-image-id : Dockerfile docker/entrypoint
	docker build -q . > $@~
	mv $@~ $@
