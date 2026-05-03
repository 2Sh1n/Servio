///Same model is used for the Slider Image & banner of featured sections in Home Screen
class SliderImage {
  SliderImage({
    required this.id,
    required this.type,
    required this.typeId,
    required this.sliderImage,
    required this.categoryName,
    required this.providerName,
    required this.url,
  });

  SliderImage.fromJson(final Map<String?, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    type = (json["type"]?.toString() == "Category" ||
            json["banner_type"]?.toString() == "banner_category")
        ? (json['category_parent_id']?.toString() == "0")
            ? SliderImagesType.category
            : SliderImagesType.subCategory
        : (json["type"]?.toString() == "provider" ||
                json["banner_type"]?.toString() == "banner_provider")
            ? SliderImagesType.provider
            : (json["type"]?.toString() == "url" ||
                    json["banner_type"]?.toString() == "banner_url")
                ? SliderImagesType.url
                : SliderImagesType.defaultType;
    typeId = json["type_id"]?.toString() ?? '';
    sliderImage = json["slider_app_image"]?.toString() ??
        json['app_banner_image']?.toString();
    categoryName = json["category_name"]?.toString() ?? '';
    providerName = json["provider_name"]?.toString() ?? '';
    url = json["url"]?.toString() ?? json["banner_url"]?.toString() ?? '';
  }

  String? id;
  SliderImagesType? type;
  String? typeId;
  String? sliderImage;
  String? categoryName;
  String? providerName;
  String? url;
}

enum SliderImagesType {
  category,
  subCategory,
  provider,
  url,
  defaultType,
}
