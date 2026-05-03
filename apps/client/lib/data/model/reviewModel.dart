class Reviews {
  Reviews(
      {this.id,
      this.userId,
      this.partnerName,
      this.rating,
      this.providerId,
      this.userName,
      this.profileImage,
      this.serviceId,
      this.comment,
      this.images,
      this.ratedOn,
      this.rateUpdatedOn,
      this.translatedServiceName,
      this.serviceName});

  Reviews.fromJson(final Map<String?, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    userId = json["user_id"]?.toString() ?? '';
    providerId = json["partner_id"]?.toString() ?? '';
    partnerName = json["partner_name"] ?? '';
    rating = json["rating"]?.toString() ?? '';
    userName = json["user_name"]?.toString() ?? '';
    profileImage = json["profile_image"]?.toString() ?? '';
    serviceId = json["service_id"]?.toString() ?? '';
    comment = json["comment"]?.toString() ?? '';
    images = json["images"] ?? [];
    ratedOn = json["rated_on"]?.toString() ?? '';
    rateUpdatedOn = json["rate_updated_on"]?.toString() ?? '';
    serviceName = json["service_name"]?.toString() ?? '';
    translatedServiceName =
        (json["translated_service_name"]?.toString() ?? '').isNotEmpty
            ? json["translated_service_name"]!.toString()
            : (json["service_name"]?.toString() ?? '');
  }

  String? id;
  String? userId;
  String? providerId;
  String? partnerName;
  String? userName;
  String? profileImage;
  String? serviceId;
  String? rating;
  String? comment;
  List<dynamic>? images;
  String? ratedOn;
  String? rateUpdatedOn;
  String? serviceName;
  String? translatedServiceName;
}
