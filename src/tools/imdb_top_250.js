$('.titleColumn').each(function() {
	var row = $(this);
	var id = row
		.find('a')
		.attr('href')
		.replace(/\?.*/, '')
		.replace(/[^0-9]/g, '');
	
	$.ajax({
	    type: 'GET',
	    url: 'http://www.imdb.com/title/tt' + id + '/'
	})
	.done(function (html) {
		var php_array = "array('id' => " + id + " , 'r' => " + row.find('span[name=ir]').attr('data-value').substr(0, 3) + ", 't' => '" + row.find('a').text().replace(/'/g, "\\'") + "', 'y' => " + row.find('.secondaryInfo').text().replace(/[^0-9]/g, '');
		var cats = ", 'c' => array(";
		var categories = html.match(/itemprop="genre">([^<]+)<\/span>/g);

		for (var i = 0; i < categories.length; i++)
			cats += "'" + categories[i].match(/itemprop="genre">([^<]+)<\/span>/)[1].toLowerCase() + "', ";

		php_array += cats.substr(0, cats.length - 2) + ") ),";

        console.log(php_array);
    });
});