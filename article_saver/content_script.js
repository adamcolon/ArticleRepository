// Object to hold information about the current page

function getImageList(){
	var results = [];
	var img_list = document.querySelectorAll("img");

	if(img_list){
		for(var i=0; i < img_list.length; ++i){
			results.push(img_list[i].src);
		}
	}
	return results;
}
var Images = getImageList();
var pageInfo = {
    "title": document.title,
    "url": window.location.href,
    "summary": window.getSelection().toString(),
	"images":Images
};

// Send the information back to the extension
chrome.extension.sendRequest(pageInfo);