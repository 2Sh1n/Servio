import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class ServiceImagesList extends StatelessWidget {
  final List<String> imageList;

  const ServiceImagesList({super.key, required this.imageList});

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: CustomSizedBox(
        height: context.screenHeight - MediaQuery.of(context).padding.top,
        child: Scaffold(
          appBar: UiUtils.getSimpleAppBar(
            context: context,
            title: "photos".translate(context: context),
          ),
          body: GridView.count(
            padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 15),
            physics: const ClampingScrollPhysics(),
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            crossAxisCount: 2,
            children: List.generate(
              imageList.length,
              (index) => CustomInkWellContainer(
                onTap: () {
                  Navigator.pushNamed(
                    context,
                    imagePreview,
                    arguments: {
                      "startFrom": index,
                      "isReviewType": false,
                      "dataURL": imageList,
                    },
                  );
                },
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(15),
                  child: CustomCachedNetworkImage(
                    networkImageUrl: imageList[index],
                    fit: BoxFit.cover,
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
