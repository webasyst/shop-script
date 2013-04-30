<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
                xmlns:html="http://www.w3.org/TR/REC-html40"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
    <xsl:template match="/">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <title>Сведения о заказах в формате CommerceML 2</title>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <style type="text/css">
                    body {
                        font-size: 12px;
                        font-family: 'Lucida Grande', Helvetica, Arial, sans-serif;
                    }
                    table { border: 0; font-size: 11px; margin-top: 20px; margin-left: -5px; }
                    table tr:nth-child(odd) td { background: #f2f7ff; }
                    table td { padding: 5px; border: 0; margin: 0; vertical-align: middle; background: #fff;  }
                    table th { text-align: left; padding: 5px; font-weight: bold; background: #fff; }
                    table tr:hover td { background: #ffffe5; }
                </style>
            </head>
            <body>
                <h1>Сведения о заказах в формате CommerceML <xsl:value-of select="КоммерческаяИнформация[@ВерсияСхемы]/@ВерсияСхемы"/></h1>
                <p>
                    Сведения о заказах до <xsl:value-of select="КоммерческаяИнформация[@ДатаФормирования]/@ДатаФормирования"/>
                </p>
                <div id="content">
                    <table>
                        <tr style="border-bottom:1px black solid;">
                            <th>Номер</th>
                            <th>Дата</th>
                            <th>Сумма</th>
                            <th>Валюта</th>
                            <th>Курс</th>
                            <th>Покупатель</th>
                            <th>Способ оплаты</th>
                            <th>Способ доставки</th>
                            <th>Адрес доставки</th>
                            <th>Состав заказа</th>
                        </tr>
                        <xsl:for-each select="КоммерческаяИнформация/Документ">
                         <xsl:variable name="currency">
                             <xsl:value-of select="Валюта"/>
                        </xsl:variable>
                            <tr>
                                <td>
                                    <xsl:value-of select="Номер"/>
                                </td>
                                <td>
                                    <xsl:value-of select="Дата"/>
                                </td>
                                <td>
                                    <xsl:value-of select="Сумма"/>
                                </td>
                                <td>
                                    <xsl:value-of select="Валюта"/>
                                </td>
                                <td>
                                    <xsl:value-of select="Курс"/>
                                </td>
                                <td>
                                    <xsl:value-of select="Контрагенты/Контрагент[Роль='Покупатель']/ПолноеНаименование"/>
                                </td>
                                
                                <td>
                                    <xsl:for-each select="ЗначенияРеквизитов/ЗначениеРеквизита">
                                    <xsl:if test="Наименование = 'Способ оплаты'">
                                    <xsl:value-of select="Значение"/>
                                    </xsl:if>
                                    </xsl:for-each>
                                </td>
                                
                                
                                
                                <td>
                                    <xsl:for-each select="ЗначенияРеквизитов/ЗначениеРеквизита">
                                    <xsl:if test="Наименование = 'Способ доставки'">
                                    <xsl:value-of select="Значение"/>
                                    </xsl:if>
                                    </xsl:for-each>
                                </td>
                                
                                <td>
                                    <xsl:for-each select="Контрагенты/Контрагент[Роль='Покупатель']/АдресРегистрации[Вид='Адрес доставки']/АдресноеПоле">
                                     <xsl:variable name="itemTitle">
                                        <xsl:value-of select="Тип"/>
                                    </xsl:variable>
                                    <xsl:text> </xsl:text>
                                    <span title="{$itemTitle}">
                                        <xsl:value-of select='Значение'/>
                                    </span>
                                    
                                    </xsl:for-each>
                                </td>
                                
                                
                                <td>
                                    <ul>
                                    <xsl:for-each select="Товары/Товар">
                                    <xsl:if test="Ид != 'ORDER_DELIVERY'">
                                    <li>
                                    <xsl:value-of select="Наименование"/><xsl:text> </xsl:text><xsl:value-of select="Количество"/><xsl:value-of select="БазоваяЕдиница"/> 
                                     <xsl:text> по </xsl:text><xsl:value-of select="Сумма"/>
                                    </li>
                                    </xsl:if>
                                    </xsl:for-each>
                                    </ul>
                                    
                                </td>
                            </tr>
                        </xsl:for-each>
                    </table>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>