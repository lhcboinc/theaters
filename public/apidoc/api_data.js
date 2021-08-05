define({ "api": [
  {
    "type": "get",
    "url": "/api/performances",
    "title": "Get Performances",
    "name": "GetPerformances",
    "group": "Performances",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "integer",
            "optional": false,
            "field": "limit",
            "description": ""
          },
          {
            "group": "Parameter",
            "type": "string",
            "allowedValues": [
              "theater",
              "movie"
            ],
            "optional": false,
            "field": "type",
            "description": ""
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "../app/Http/Controllers/Api/PerformancesController.php",
    "groupTitle": "Performances"
  }
] });
