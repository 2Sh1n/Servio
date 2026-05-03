class Services {
  String? id;
  String? userId;
  String? categoryId;
  String? categoryName;
  String? partnerName;
  String? translatedPartnerName;
  String? taxId;
  String? title;
  String? translatedTitle;
  String? slug;
  String? description;
  String? translatedDescription;
  String? longDescription;
  String? translatedLongDescription;
  String? tags;
  String? imageOfTheService;
  List<String>? otherImagesOfTheService;
  List<String>? filesOfTheService;
  List<ServiceFaQs>? faqsOfTheService;
  List<ServiceFaQs>? translatedFaqsOfTheService;
  String? price;
  String? discountedPrice;
  String? priceWithTax;
  String? originalPriceWithTax;
  String? taxAmount;
  String? numberOfMembersRequired;
  String? duration;
  String? averageRating;
  String? rating;
  String? numberOfRatings;
  String? onSiteAllowed;
  String? maxQuantityAllowed;
  String? isPayLaterAllowed;
  String? status;
  String? cartQuantity;
  String? oneStar;
  String? twoStar;
  String? threeStar;
  String? fourStar;
  String? fiveStar;

  Services({
    required this.id,
    required this.userId,
    required this.categoryId,
    required this.categoryName,
    required this.partnerName,
    required this.translatedPartnerName,
    required this.taxId,
    required this.title,
    required this.translatedTitle,
    required this.slug,
    required this.description,
    required this.translatedDescription,
    required this.tags,
    required this.imageOfTheService,
    required this.price,
    required this.discountedPrice,
    required this.originalPriceWithTax,
    required this.taxAmount,
    required this.priceWithTax,
    required this.numberOfMembersRequired,
    required this.duration,
    required this.rating,
    required this.averageRating,
    required this.numberOfRatings,
    required this.onSiteAllowed,
    required this.maxQuantityAllowed,
    required this.isPayLaterAllowed,
    required this.status,
    required this.cartQuantity,
    required this.faqsOfTheService,
    required this.translatedFaqsOfTheService,
    required this.filesOfTheService,
    required this.longDescription,
    required this.translatedLongDescription,
    required this.otherImagesOfTheService,
    this.fiveStar,
    this.fourStar,
    this.threeStar,
    this.twoStar,
    this.oneStar,
  });

  Services.fromJson(final Map<String?, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    userId = json["userid"]?.toString() ?? '';
    categoryId = json["category_id"]?.toString() ?? '';
    categoryName = json["category_name"]?.toString() ?? '';
    partnerName = json["partner_name"]?.toString() ?? '';
    translatedTitle =
        (json["translated_partner_name"]?.toString() ?? '').isNotEmpty
            ? json["translated_partner_name"]!.toString()
            : partnerName;
    taxId = json["tax_id"]?.toString() ?? '';
    title = json["title"]?.toString() ?? '';
    translatedTitle = (json["translated_title"]?.toString() ?? '').isNotEmpty
        ? json["translated_title"]!.toString()
        : title;
    slug = json["slug"]?.toString() ?? '';
    description = json["description"]?.toString() ?? '';
    translatedDescription =
        (json["translated_description"]?.toString() ?? '').isNotEmpty
            ? json["translated_description"]!.toString()
            : description;

    imageOfTheService = json["image_of_the_service"]?.toString() ?? '';
    price = json["price"]?.toString() ?? '';
    discountedPrice = json["discounted_price"]?.toString() ?? '';
    originalPriceWithTax = json["original_price_with_tax"]?.toString() ?? '';
    taxAmount = json["tax_value"]?.toString() ?? '';
    priceWithTax = json["price_with_tax"]?.toString() ?? '';
    numberOfMembersRequired =
        json["number_of_members_required"]?.toString() ?? '';
    duration = json["duration"]?.toString() ?? '0';
    averageRating = (json["average_rating"] ?? '0').toString();
    rating = (json["rating"] ?? '0').toString();
    numberOfRatings = (json["total_ratings"] ?? '0').toString();
    onSiteAllowed = json["on_site_allowed"]?.toString() ?? '0';
    maxQuantityAllowed = json["max_quantity_allowed"]?.toString() ?? '0';
    status = json["status"]?.toString() ?? '';
    cartQuantity = json["in_cart_quantity"]?.toString() != ''
        ? json["in_cart_quantity"]?.toString()
        : "0";
    longDescription = json["long_description"]?.toString() ?? '';
    translatedLongDescription =
        (json["translated_long_description"]?.toString() ?? '').isNotEmpty
            ? json["translated_long_description"]!.toString()
            : longDescription;

    oneStar = (json["rating_1"] ?? '0').toString();
    twoStar = (json["rating_2"] ?? '0').toString();
    threeStar = (json["rating_3"] ?? '0').toString();
    fourStar = (json["rating_4"] ?? '0').toString();
    fiveStar = (json["rating_5"] ?? '0').toString();

    faqsOfTheService = (json["faqs"] != null &&
            json["faqs"] != '' &&
            json["faqs"] is List)
        ? (json["faqs"] as List).map((v) => ServiceFaQs.fromJson(v)).toList()
        : <ServiceFaQs>[];

    translatedFaqsOfTheService = (json["translated_faqs"] != null &&
            json["translated_faqs"] != '' &&
            json["translated_faqs"] is List)
        ? (json["translated_faqs"] as List)
            .map((v) => ServiceFaQs.fromJson(v))
            .toList()
        : (faqsOfTheService ?? <ServiceFaQs>[]);

    if (json["other_images"] != null &&
        json['other_images'] != '' &&
        json["other_images"] is List) {
      otherImagesOfTheService = <String>[];
      (json["other_images"] as List).forEach((final v) {
        otherImagesOfTheService!.add(v);
      });
    } else {
      otherImagesOfTheService = <String>[];
    }
    if (json["files"] != null && json["files"] != '' && json["files"] is List) {
      filesOfTheService = <String>[];
      (json["files"] as List).forEach((final v) {
        filesOfTheService!.add(v);
      });
    } else {
      filesOfTheService = <String>[];
    }
  }
}

class ServiceFaQs {
  String? question;
  String? answer;

  ServiceFaQs({
    this.question,
    this.answer,
  });

  factory ServiceFaQs.fromJson(Map<String, dynamic> json) => ServiceFaQs(
        question: json["question"]?.toString() ?? '',
        answer: json["answer"]?.toString() ?? '',
      );

  Map<String, dynamic> toJson() => {
        "question": question,
        "answer": answer,
      };
}
